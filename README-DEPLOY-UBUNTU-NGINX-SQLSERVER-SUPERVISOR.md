# Student Hub Deployment Guide (Ubuntu + Nginx + SQL Server + Supervisor)

## Deployment Architecture

```
Cloudflare (CDN + TLS) -> Ubuntu Reverse Proxy (Nginx) -> Student Hub Server (Nginx + PHP-FPM)
```

- **Cloudflare**: Handles edge caching, TLS/SSL termination, DDoS protection, and forwards traffic to your reverse proxy
- **Ubuntu Reverse Proxy**: Receives traffic from Cloudflare and forwards requests to the Student Hub server on port 4010
- **Student Hub Server**: Runs on this host at `/opt/studenthub.usm.edu.ph/student-hub` with Nginx + PHP-FPM

This guide covers **Student Hub Server** setup. The reverse proxy is a separate Ubuntu host that proxies to this server.

### Repository Stack

This guide is grounded in this repository's current setup:

- Laravel 13 with PHP `^8.3` (from `composer.json`)
- Frontend assets built with Vite (`npm run build`)
- Wayfinder generation step used in this project (`php artisan wayfinder:generate --no-interaction`)
- SQL Server connection (`DB_CONNECTION=sqlsrv`)
- Queue connection set to database (`QUEUE_CONNECTION=database`)
- SQL Server options used by app config: `DB_ENCRYPT` and `DB_TRUST_SERVER_CERTIFICATE`

## 1) Target host assumptions

- Ubuntu 24.04 LTS
- App directory: `/opt/studenthub.usm.edu.ph/student-hub`
- Web user: `www-data`
- Nginx + PHP-FPM 8.3
- SQL Server is reachable on the network

If your Ubuntu version is different, adjust package repo URLs accordingly.

## 2) Install system packages

```bash
sudo apt update
sudo apt install -y software-properties-common ca-certificates apt-transport-https lsb-release gnupg2 curl unzip git nginx supervisor
```

## 3) Install PHP 8.3 and required extensions

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y \
  php8.3-fpm php8.3-cli php8.3-common php8.3-dev \
  php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath \
  php8.3-intl php8.3-gd php8.3-soap php8.3-readline php8.3-opcache \
  php-pear build-essential unixodbc-dev
```

Install Composer:

```bash
cd /tmp
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

Install Node.js LTS (20.x) and npm:

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v
npm -v
```

## 4) Install Microsoft ODBC Driver + sqlsrv PHP extensions

Add Microsoft package feed:

```bash
curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | sudo gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg
echo "deb [arch=amd64 signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/ubuntu/24.04/prod noble main" | sudo tee /etc/apt/sources.list.d/microsoft-prod.list > /dev/null
sudo apt update
```

Install ODBC driver/tools:

```bash
sudo ACCEPT_EULA=Y apt install -y msodbcsql18 mssql-tools18 unixodbc-dev
```

Install PHP SQL Server extensions using PECL:

```bash
sudo pecl install sqlsrv pdo_sqlsrv
```

Enable extensions for PHP CLI and PHP-FPM:

```bash
echo "extension=sqlsrv.so" | sudo tee /etc/php/8.3/mods-available/sqlsrv.ini
echo "extension=pdo_sqlsrv.so" | sudo tee /etc/php/8.3/mods-available/pdo_sqlsrv.ini
sudo phpenmod sqlsrv pdo_sqlsrv
php -m | grep -E "sqlsrv|pdo_sqlsrv"
```

Restart PHP-FPM:

```bash
sudo systemctl restart php8.3-fpm
sudo systemctl enable php8.3-fpm
```

## 5) Deploy application code

```bash
sudo mkdir -p /opt/studenthub.usm.edu.ph
sudo chown -R $USER:$USER /opt/studenthub.usm.edu.ph
cd /opt/studenthub.usm.edu.ph
git clone <YOUR_REPO_URL> student-hub
cd student-hub
```

Install dependencies and build assets:

```bash
composer install --no-dev --optimize-autoloader
npm ci
php artisan wayfinder:generate --no-interaction
npm run build
```

## 6) Configure environment

Use the repo's production template as baseline:

```bash
cp .env.production .env
```

Edit `.env` and validate at minimum:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://studenthub.usm.edu.ph` (public Cloudflare URL)
- `DB_CONNECTION=sqlsrv`
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `DB_ENCRYPT` (`yes` or `no`)
- `DB_TRUST_SERVER_CERTIFICATE` (`true` or `false`)
- `QUEUE_CONNECTION=database`
- `TRUSTED_PROXIES=*` (allows Laravel to trust X-Forwarded-* headers from the reverse proxy)

Important:

- Never commit real credentials in `.env` files.
- If `.env.production` currently contains real secrets, rotate them before or immediately after deployment.
- `TRUSTED_PROXIES=*` is required for Laravel to correctly identify user IPs and schemes when behind a reverse proxy.
- Ensure the reverse proxy passes `X-Forwarded-For`, `X-Forwarded-Proto`, and `X-Forwarded-Host` headers (handled in the proxy config above).

## 7) Laravel one-time setup

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If first-time bootstrap needs seed data:

```bash
php artisan db:seed --force
```

## 8) File permissions

```bash
sudo chown -R www-data:www-data /opt/studenthub.usm.edu.ph/student-hub
sudo find /opt/studenthub.usm.edu.ph/student-hub -type f -exec chmod 644 {} \;
sudo find /opt/studenthub.usm.edu.ph/student-hub -type d -exec chmod 755 {} \;
sudo chmod -R 775 /opt/studenthub.usm.edu.ph/student-hub/storage /opt/studenthub.usm.edu.ph/student-hub/bootstrap/cache
```

## 9) Nginx site config

Create `/etc/nginx/sites-available/student-hub`:

```nginx
upstream php_backend {
    server unix:/run/php/php8.3-fpm.sock;
}

server {
    listen 4010;
    server_name _;  # Student Hub server listens internally only

    root /opt/studenthub.usm.edu.ph/student-hub/public;
    index index.php;

    # Trust reverse proxy headers from Cloudflare -> Reverse Proxy chain
    set_real_ip_from 10.0.0.0/8;
    set_real_ip_from 172.16.0.0/12;
    set_real_ip_from 192.168.0.0/16;
    real_ip_header X-Forwarded-For;
    real_ip_recursive on;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass php_backend;
        # Forward client IP and original scheme from reverse proxy
        fastcgi_param HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for;
        fastcgi_param HTTP_X_FORWARDED_PROTO $http_x_forwarded_proto;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable and reload:

```bash
sudo ln -sf /etc/nginx/sites-available/student-hub /etc/nginx/sites-enabled/student-hub
sudo nginx -t
sudo systemctl restart nginx
sudo systemctl enable nginx
```

### Reverse Proxy Configuration (separate Ubuntu host)

On your **Ubuntu Reverse Proxy** host, configure Nginx to forward traffic to this Student Hub server on port 8080.

Create `/etc/nginx/sites-available/studenthub-proxy`:

```nginx
server {
    listen 80;
    server_name studenthub.usm.edu.ph;

    # Forward all requests to Student Hub server backend
    location / {
        proxy_pass http://<STUDENT_HUB_SERVER_IP>:4010;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $server_name;
        proxy_redirect off;
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }
}
```

Replace `<STUDENT_HUB_SERVER_IP>` with the internal IP of the Student Hub server.

Enable and reload:

```bash
sudo ln -sf /etc/nginx/sites-available/studenthub-proxy /etc/nginx/sites-enabled/studenthub-proxy
sudo nginx -t
sudo systemctl restart nginx
```

### Cloudflare Configuration

1. Point your DNS record `studenthub.usm.edu.ph` to your **reverse proxy** host IP in Cloudflare
2. In Cloudflare dashboard:
   - Enable **SSL/TLS** (Full or Full Strict recommended)
   - Enable **Caching Rules** if needed
   - Configure **Page Rules** for performance/security
3. Traffic flow: **Cloudflare** → **Reverse Proxy** (port 80) → **Student Hub Server** (port 4010)

## 10) Supervisor for queue worker

Create `/etc/supervisor/conf.d/student-hub-queue.conf`:

```ini
[program:student-hub-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /opt/studenthub.usm.edu.ph/student-hub/artisan queue:work database --sleep=3 --tries=3 --timeout=120 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/opt/studenthub.usm.edu.ph/student-hub/storage/logs/queue-worker.log
stopwaitsecs=3600
```

Apply changes:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start student-hub-queue:*
sudo supervisorctl status
```

## 11) Supervisor for scheduler (optional but recommended)

Current repository has no scheduled tasks defined in `routes/console.php`, but running the scheduler keeps deployment future-proof.

Create `/etc/supervisor/conf.d/student-hub-scheduler.conf`:

```ini
[program:student-hub-scheduler]
command=php /opt/studenthub.usm.edu.ph/student-hub/artisan schedule:work
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/opt/studenthub.usm.edu.ph/student-hub/storage/logs/scheduler.log
```

Apply:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start student-hub-scheduler
sudo supervisorctl status
```

## 12) Smoke checks

```bash
php artisan about
php artisan queue:monitor database:default --max=100
php -m | grep -E "sqlsrv|pdo_sqlsrv"
sudo tail -n 100 /var/log/nginx/error.log
sudo tail -n 100 /opt/studenthub.usm.edu.ph/student-hub/storage/logs/laravel.log
sudo supervisorctl status
```

## 13) Update workflow (subsequent releases)

```bash
cd /opt/studenthub.usm.edu.ph/student-hub
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci
php artisan wayfinder:generate --no-interaction
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl restart student-hub-queue:*
sudo supervisorctl restart student-hub-scheduler
```

## 14) Common SQL Server issues

- `could not find driver`: sqlsrv/pdo_sqlsrv extension not loaded in FPM. Re-check `phpenmod` and restart `php8.3-fpm`.
- Login/SSL handshake errors: verify `DB_ENCRYPT` and `DB_TRUST_SERVER_CERTIFICATE` values for your SQL Server environment.
- Timeouts to SQL Server host: check firewall between Ubuntu host and SQL Server on port `1433`.
