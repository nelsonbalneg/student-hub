# Deploy Script Setup Guide

The `deploy.sh` script automates the full deployment workflow. Use one of the methods below to trigger it automatically when code is pushed to the `main` branch.

## Prerequisites

1. Make the script executable:

```bash
chmod +x /opt/studenthub.usm.edu.ph/student-hub/deploy.sh
```

2. Configure sudoers so deployment can restart Supervisor without password:

```bash
sudo visudo
```

Add these lines at the end:

```
www-data ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl
```

Save and exit (Ctrl+X, Y, Enter if using nano).

## Option 1: GitHub Webhook (Recommended for automatic deployments)

This method triggers the deployment script automatically when code is pushed to `main`.

### 1.1) Create a webhook receiver script

Create `/opt/studenthub.usm.edu.php/webhook-receiver.php`:

```php
<?php
/**
 * GitHub Webhook Receiver
 * 
 * Receives push events from GitHub and triggers the deploy script.
 * Place this outside the public web root or behind authentication.
 */

$webhook_secret = getenv('GITHUB_WEBHOOK_SECRET') ?: $_ENV['GITHUB_WEBHOOK_SECRET'] ?? null;

if (!$webhook_secret) {
    http_response_code(400);
    die('GITHUB_WEBHOOK_SECRET not configured');
}

// Verify webhook signature
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$payload = file_get_contents('php://input');
$expected_signature = 'sha256=' . hash_hmac('sha256', $payload, $webhook_secret);

if (!hash_equals($expected_signature, $signature)) {
    http_response_code(401);
    die('Invalid webhook signature');
}

// Parse webhook payload
$event = json_decode($payload, true);

// Only deploy on main branch pushes
if ($event['ref'] !== 'refs/heads/main') {
    http_response_code(200);
    die('Ignoring non-main branch push');
}

// Log the webhook event
$log_file = '/opt/studenthub.usm.edu.ph/student-hub/storage/logs/webhook.log';
file_put_contents(
    $log_file,
    date('Y-m-d H:i:s') . " - Webhook received. Triggering deployment.\n",
    FILE_APPEND
);

// Trigger deployment script via background process
$deploy_script = '/opt/studenthub.usm.edu.ph/student-hub/deploy.sh';
$output_log = '/opt/studenthub.usm.edu.ph/student-hub/storage/logs/deploy.log';

// Run deploy script in background
exec("sudo -u www-data $deploy_script >> $output_log 2>&1 &");

http_response_code(202);
echo json_encode(['status' => 'Deployment triggered']);
?>
```

### 1.2) Configure the webhook receiver in .env

Add to `.env`:

```env
GITHUB_WEBHOOK_SECRET=your_webhook_secret_here
```

Generate a secure secret:

```bash
openssl rand -hex 32
```

### 1.3) Set up an endpoint for the webhook

If you want to expose this via HTTP, configure Nginx to forward webhook requests:

```nginx
location /webhook/github {
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    include snippets/fastcgi-php.conf;
    fastcgi_param SCRIPT_FILENAME /opt/studenthub.usm.edu.ph/webhook-receiver.php;
}
```

Or simpler: trigger the deploy script directly from a systemd service (see Option 2).

### 1.4) Configure GitHub webhook

1. Go to your GitHub repository
2. Settings → Webhooks → Add webhook
3. **Payload URL**: `https://studenthub.usm.edu.ph/webhook/github` (if using HTTP endpoint)
   - Or set up to call the script directly via a service
4. **Content type**: `application/json`
5. **Secret**: Paste your webhook secret
6. **Events**: Select "Just the push event"
7. Click "Add webhook"

---

## Option 2: Cron job (Simple polling for deployments)

This method checks for new commits every few minutes and deploys if needed.

### 2.1) Create a check-and-deploy wrapper script

Create `/opt/studenthub.usm.edu.ph/student-hub/check-and-deploy.sh`:

```bash
#!/bin/bash

APP_DIR="/opt/studenthub.usm.edu.ph/student-hub"
LAST_COMMIT_FILE="$APP_DIR/.deploy-last-commit"
LOG_FILE="$APP_DIR/storage/logs/check-and-deploy.log"

cd "$APP_DIR" || exit 1

# Fetch latest commits without modifying local repo
git fetch origin main >> "$LOG_FILE" 2>&1

# Get the latest commit hash on origin/main
LATEST_COMMIT=$(git rev-parse origin/main)

# Get the last deployed commit hash
LAST_DEPLOYED=""
if [ -f "$LAST_COMMIT_FILE" ]; then
    LAST_DEPLOYED=$(cat "$LAST_COMMIT_FILE")
fi

# If commits differ, trigger deployment
if [ "$LATEST_COMMIT" != "$LAST_DEPLOYED" ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - New commits detected. Triggering deployment..." >> "$LOG_FILE"
    
    # Run deploy script
    if /opt/studenthub.usm.edu.ph/student-hub/deploy.sh >> "$LOG_FILE" 2>&1; then
        # Store the deployed commit hash
        echo "$LATEST_COMMIT" > "$LAST_COMMIT_FILE"
        echo "$(date '+%Y-%m-%d %H:%M:%S') - Deployment successful." >> "$LOG_FILE"
    else
        echo "$(date '+%Y-%m-%d %H:%M:%S') - Deployment failed." >> "$LOG_FILE"
    fi
else
    echo "$(date '+%Y-%m-%d %H:%M:%S') - No new commits." >> "$LOG_FILE"
fi
```

Make it executable:

```bash
chmod +x /opt/studenthub.usm.edu.ph/student-hub/check-and-deploy.sh
```

### 2.2) Add cron job

Edit crontab as www-data:

```bash
sudo -u www-data crontab -e
```

Add one of these lines:

**Every 5 minutes:**
```cron
*/5 * * * * /opt/studenthub.usm.edu.ph/student-hub/check-and-deploy.sh
```

**Every 15 minutes:**
```cron
*/15 * * * * /opt/studenthub.usm.edu.ph/student-hub/check-and-deploy.sh
```

**Every hour:**
```cron
0 * * * * /opt/studenthub.usm.edu.ph/student-hub/check-and-deploy.sh
```

---

## Option 3: GitHub Actions (If using GitHub)

Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy to Production

on:
  push:
    branches:
      - main

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Trigger deployment
        env:
          DEPLOY_KEY: ${{ secrets.DEPLOY_KEY }}
          DEPLOY_HOST: ${{ secrets.DEPLOY_HOST }}
          DEPLOY_USER: ${{ secrets.DEPLOY_USER }}
        run: |
          mkdir -p ~/.ssh
          echo "$DEPLOY_KEY" > ~/.ssh/deploy_key
          chmod 600 ~/.ssh/deploy_key
          ssh-keyscan -H $DEPLOY_HOST >> ~/.ssh/known_hosts
          ssh -i ~/.ssh/deploy_key $DEPLOY_USER@$DEPLOY_HOST "cd /opt/studenthub.usm.edu.ph/student-hub && /opt/studenthub.usm.edu.ph/student-hub/deploy.sh"
```

Requires GitHub Secrets:
- `DEPLOY_KEY`: Private SSH key (generate with `ssh-keygen`)
- `DEPLOY_HOST`: IP or hostname of Student Hub Server
- `DEPLOY_USER`: SSH user (usually `www-data` or a deployment user)

---

## Manual Deployment

To deploy manually anytime:

```bash
cd /opt/studenthub.usm.edu.ph/student-hub
sudo -u www-data ./deploy.sh
```

Or run as root:

```bash
sudo /opt/studenthub.usm.edu.ph/student-hub/deploy.sh
```

---

## Monitoring Deployments

View deployment logs:

```bash
tail -f /opt/studenthub.usm.edu.ph/student-hub/storage/logs/deploy.log
```

View webhook logs (if using webhook):

```bash
tail -f /opt/studenthub.usm.edu.ph/student-hub/storage/logs/webhook.log
```

Check Supervisor process status:

```bash
sudo supervisorctl status
```

---

## Rollback on Failed Deployment

If a deployment fails, manually rollback to previous commit:

```bash
cd /opt/studenthub.usm.edu.ph/student-hub
git log --oneline -10  # View recent commits
git reset --hard <COMMIT_HASH>  # Rollback to a specific commit
```

Then restart services:

```bash
sudo supervisorctl restart student-hub-queue:*
sudo supervisorctl restart student-hub-scheduler
```

---

## Troubleshooting

**Deploy script exits with permission denied:**
- Ensure www-data user can run commands without password in sudoers
- Check that `/opt/studenthub.usm.edu.ph/student-hub/deploy.sh` is executable

**Migrations fail:**
- Check database credentials in `.env`
- Ensure SQL Server is reachable
- Review migration errors in logs

**npm build fails:**
- Check Node.js version: `node -v` (should be 18+)
- Clear npm cache: `npm cache clean --force`
- Delete `node_modules` and `package-lock.json`, then `npm ci` again

**Supervisor restart fails:**
- Check supervisord logs: `sudo tail -f /var/log/supervisor/supervisord.log`
- Verify supervisor config syntax: `sudo supervisorctl reread`

**Git pull fails:**
- Check SSH keys for GitHub access
- Verify branch exists: `git branch -a`
- Check network connectivity to GitHub
