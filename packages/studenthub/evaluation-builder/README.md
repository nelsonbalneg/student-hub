# StudentHub Evaluation Builder

Reusable Laravel core for building configurable evaluation templates with sections, questions, choices, scale sets, scoring rules, and interpretation ranges.

## Installation

```bash
composer require studenthub/evaluation-builder
php artisan vendor:publish --tag=evaluation-builder-config
php artisan migrate
```

For local development, add a Composer path repository:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "packages/studenthub/evaluation-builder",
      "options": { "symlink": true }
    }
  ]
}
```

## Custom models

Publish `config/evaluation-builder.php` and replace any model class. Host applications can extend the package models to add authorization, user relationships, scopes, or domain-specific behavior.

## Payload service

```php
use StudentHub\EvaluationBuilder\Services\TemplatePayloadService;

$payload = app(TemplatePayloadService::class)->build($template);
```

The returned structure is suitable for web forms, mobile clients, and API responses.

## Publishing

```bash
php artisan vendor:publish --tag=evaluation-builder-config
php artisan vendor:publish --tag=evaluation-builder-migrations
```
