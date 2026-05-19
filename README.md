# Student Hub

Laravel 13 + Vue/Inertia student services portal.

This README documents the recent additions and modifications for:

- Legal Documents management
- Terms and Conditions acceptance flow
- Sticky legal footer links
- User Management server-side table pagination
- Core performance index migration

## Requirements

- PHP 8.3+
- Composer
- Node.js and npm
- SQL Server connection configured in `.env`
- Spatie Laravel Permission tables migrated

## Setup

Install dependencies:

```bash
composer install
npm install
```

Generate frontend route helpers and build assets:

```bash
php artisan wayfinder:generate --no-interaction
npm run build
```

Run migrations:

```bash
php artisan migrate
```

Seed permissions and default legal documents:

```bash
php artisan db:seed --class=LegalPermissionsSeeder
php artisan db:seed --class=LegalDocumentsSeeder
```

Or run the project seeder:

```bash
php artisan db:seed
```

## Legal Documents Module

Admin route:

```text
/settings/legal
```

Public/legal fetch route:

```text
GET /legal/{type}
```

Allowed types:

- `terms`
- `cookie_policy`
- `privacy_policy`

Terms acceptance route:

```text
POST /legal/accept-terms
```

### Main Files

Backend:

- `app/Models/LegalDocument.php`
- `app/Models/UserLegalAcceptance.php`
- `app/Services/LegalDocumentService.php`
- `app/Http/Controllers/LegalDocumentController.php`
- `app/Http/Controllers/LegalPublicController.php`
- `app/Http/Middleware/EnsureTermsAccepted.php`
- `app/Http/Requests/StoreLegalDocumentRequest.php`
- `app/Http/Requests/UpdateLegalDocumentRequest.php`

Frontend:

- `resources/js/pages/Legal/Index.vue`
- `resources/js/pages/Legal/Create.vue`
- `resources/js/pages/Legal/Edit.vue`
- `resources/js/pages/Legal/Show.vue`
- `resources/js/pages/Legal/Form.vue`
- `resources/js/components/LegalFooter.vue`
- `resources/js/components/LegalDocumentDrawer.vue`
- `resources/js/components/TermsAcceptanceModal.vue`

Database:

- `database/migrations/2026_05_14_013207_create_legal_documents_table.php`
- `database/migrations/2026_05_14_013208_create_user_legal_acceptances_table.php`
- `database/seeders/LegalPermissionsSeeder.php`
- `database/seeders/LegalDocumentsSeeder.php`

### Permissions

The Legal module uses Spatie permissions:

- `legal.view`
- `legal.create`
- `legal.edit`
- `legal.delete`
- `legal.activate`

The sidebar item `Settings -> Legal` is visible only to users with `legal.view` or Super Admin access.

### Behavior

- Admins can create, edit, view, delete, activate, and deactivate legal documents.
- Only one active document is allowed per type.
- Activating a document deactivates any other active document of the same type.
- Legal content is stored in the database. Do not hardcode legal text in Vue files.
- Default legal documents are seeded as active with version `1.0`.
- Acceptance is version-aware. If a new active Terms and Conditions version is activated, users must accept it again.

## Terms Acceptance Flow

Authenticated users are checked against the active Terms and Conditions document.

Users must accept terms when:

- They have never accepted the active terms.
- The active terms document changed.
- The active terms version changed.

The required acceptance modal:

- Displays the active Terms and Conditions content from the database.
- Requires the checkbox before enabling the accept button.
- Records `user_id`, `legal_document_id`, `type`, `version`, `accepted_at`, `ip_address`, and `user_agent`.
- Cannot be dismissed without accepting.

The middleware alias is registered as:

```php
terms.accepted
```

It is applied to authenticated/verified application routes, with these exceptions:

- Logout
- `GET /legal/{type}`
- `POST /legal/accept-terms`

## Sticky Legal Footer

The sticky footer appears in:

- Auth layouts such as login/register
- Authenticated app layout

Footer links:

- Terms and Conditions
- Cookie Policy
- Privacy Policy

Clicking a link opens a right-side drawer and fetches the active document through:

```text
GET /legal/{type}
```

No page reload is required.

## User Management Table Pagination

The User Management page was updated for server-side pagination and large datasets.

Route:

```text
/user-management
```

Behavior:

- Default page size is `10`.
- Users can choose `10`, `25`, `50`, or `100` rows.
- Search, filters, and `per_page` are sent to the server.
- Pagination links preserve current query parameters.
- Selection state resets when the page data changes.

Important files:

- `app/Http/Controllers/UserManagementController.php`
- `resources/js/pages/UserManagement/Users.vue`

## Performance Index Migration

A forward migration was added for indexes based on current query patterns:

```text
database/migrations/2026_05_14_012120_add_performance_indexes_to_core_tables.php
```

It covers common filters, joins, status/date pagination, and lookup paths across:

- Users
- Permissions
- Announcements
- Clearance
- Evaluation
- FAQs
- Internet account requests
- Site settings

The migration is defensive:

- It skips missing tables.
- It skips missing columns.
- It skips indexes that already exist by name.
- It provides a reversible `down()` method.

## SQL Server Notes

This project uses SQL Server in local `.env`.

For legal document audit columns, `created_by` and `updated_by` use:

```php
noActionOnDelete()
```

This avoids SQL Server multiple cascade path errors.

If a failed migration created a partial table, drop the partial table before re-running migrations.

Example:

```sql
DROP TABLE legal_documents;
```

Then run:

```bash
php artisan migrate
```

## Verification Commands

Run PHP syntax checks:

```bash
php -l app/Http/Controllers/LegalDocumentController.php
php -l app/Http/Controllers/LegalPublicController.php
php -l app/Http/Middleware/EnsureTermsAccepted.php
```

Run formatter check:

```bash
vendor/bin/pint --dirty --test
```

Build frontend assets:

```bash
npm run build
```

Check legal routes:

```bash
php artisan route:list --path=legal
php artisan route:list --path=settings/legal
```

## Ubuntu + Nginx Deployment

See [DEPLOYMENT-UBUNTU-NGINX.md](DEPLOYMENT-UBUNTU-NGINX.md) for a production deployment guide on Ubuntu with Nginx and PHP-FPM.

## Development Notes

- Regenerate Wayfinder after route changes:

```bash
php artisan wayfinder:generate --no-interaction
```

- Keep legal text in the database through the Legal admin UI or seeders.
- Use Spatie permissions for route, button, and sidebar visibility.
- Preserve SQL Server-safe foreign key behavior when adding user audit columns.
