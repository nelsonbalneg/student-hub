# Ubuntu + Nginx Deployment Guide

This guide covers deploying the Student Hub Laravel application on Ubuntu with Nginx and PHP-FPM.

## Prerequisites

- Ubuntu 22.04+ or 24.04
- Nginx
- PHP 8.3 with FPM
- Composer
- Node.js and npm
- A supported database: MySQL, MariaDB, PostgreSQL, or SQL Server
- `git` (optional)

## 1. Server setup

Update packages and install runtime dependencies:

```bash
sudo apt update
sudo apt install -y nginx git curl unzip
sudo apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-mbstring php8.3-xml php8.3-bcmath php8.3-json php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-pdo php8.3-sqlite3 php8.3-pgsql php8.3-mysql php8.3-redis
```

If you need SQL Server support instead of MySQL/Postgres, install the Microsoft ODBC and PHP drivers for SQL Server.

## 2. Application code

Deploy the repository to `/var/www/studenthub` or another directory.

```bash
sudo mkdir -p /var/www/studenthub
sudo chown -R $(whoami):$(whoami) /var/www/studenthub
cd /var/www/studenthub
git clone <repo-url> .
```

## 3. Install PHP and frontend dependencies

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

## 4. Environment configuration

Copy the example environment and set production values.

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-domain.example`
- Database settings: `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- SSO settings: `SSO_BASE_URL`, `SSO_CLIENT_ID`, `SSO_CLIENT_SECRET`, `SSO_REDIRECT_URI`

If you are using SQL Server, also set `DB_ENCRYPT` and `DB_TRUST_SERVER_CERTIFICATE` as needed.

## 5. File permissions

```bash
sudo chown -R www-data:www-data /var/www/studenthub/storage /var/www/studenthub/bootstrap/cache
sudo chmod -R 775 /var/www/studenthub/storage /var/www/studenthub/bootstrap/cache
```

## 6. Application optimization

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan wayfinder:generate --no-interaction
```

## 7. Database migration and seeding

```bash
php artisan migrate --force
php artisan db:seed --force
```

If you only want required seeds, use the appropriate seeder classes instead.

## 8. Nginx configuration

Create a site config at `/etc/nginx/sites-available/studenthub`:

```nginx
server {
    listen 80;
    server_name your-domain.example;

    root /var/www/studenthub/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

Enable the site and reload Nginx:

```bash
sudo ln -s /etc/nginx/sites-available/studenthub /etc/nginx/sites-enabled/studenthub
sudo nginx -t
sudo systemctl restart nginx
sudo systemctl restart php8.3-fpm
```

## 9. SSL using Certbot

If you want HTTPS, install Certbot and issue a certificate:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.example
```

## 10. Background jobs and queue workers

If your deployment uses queues, create a `systemd` service for the Laravel queue worker or run it using a process manager.

Example `systemd` unit:

```ini
[Unit]
Description=Laravel Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /var/www/studenthub/artisan queue:work --sleep=3 --tries=3 --timeout=90

[Install]
WantedBy=multi-user.target
```

Enable it:

```bash
sudo systemctl daemon-reload
sudo systemctl enable laravel-queue-worker
sudo systemctl start laravel-queue-worker
```

## 11. Common production commands

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan wayfinder:generate --no-interaction
npm run build
```

## 12. Troubleshooting

- Check Nginx logs: `/var/log/nginx/error.log`
- Check PHP-FPM logs: `/var/log/php8.3-fpm.log`
- Check Laravel logs: `storage/logs/laravel.log`
- Ensure `.env` values are correct and readable by the web server

## Notes

- This repository supports Laravel 13 and requires PHP 8.3.
- The app uses SSO auth, so production SSO URLs must match the values in `.env`.
- If the server uses SQL Server, install the appropriate ODBC and PHP SQLSRV extensions.
