# Production Error Log Viewer

The secure log viewer is available at `/system/logs` for authorized administrators only.

## Security

- Keep `APP_ENV=production` and `APP_DEBUG=false` in production.
- Keep `LOG_VIEWER_ENABLED=false` unless you intentionally want to expose the package's own route. StudentHub uses the custom protected `/system/logs` route.
- Access is limited to users with the `Super Admin` role, `System Administrator` role, or the relevant `system.logs.*` permissions.
- Clearing logs is limited to `Super Admin` or `system.logs.clear`.

## Deployment

Run these after pulling the release:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan wayfinder:generate
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm ci
npm run build
```

The package assets and config were published with:

```bash
php artisan log-viewer:publish
php artisan vendor:publish --provider="Opcodes\LogViewer\LogViewerServiceProvider" --tag="log-viewer-config"
php artisan vendor:publish --provider="Opcodes\LogViewer\LogViewerServiceProvider" --tag="log-viewer-views"
```

## Audit Trail

The module records these actions in `audit_logs` when the table exists:

- Viewed Logs
- Downloaded Logs
- Exported Logs
- Cleared Logs

## Notes

Log parsing reads bounded tail chunks and paginates server-side to avoid loading full log files into memory. Downloads stream the selected file directly from `storage/logs`.
