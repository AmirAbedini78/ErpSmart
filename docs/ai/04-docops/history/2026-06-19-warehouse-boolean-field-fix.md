# 2026-06-19 — Warehouse Boolean Field Fix

## Problem
Creating a Warehouse failed with SQL error:

```text
SQLSTATE[HY000]: General error: 1366 Incorrect integer value: 'rt' for column 'is_active'
```

The API payload showed:

```json
{
  "name": "AmirHoseinAbediniNezhad",
  "code": "387651",
  "description": "asd",
  "is_active": "rt"
}
```

## Root cause
`is_active` is a boolean database column, but the Warehouse Resource exposed it as a text field:

```php
Text::make('is_active', ...)
```

Because the Resource UI is generated from backend fields, the form rendered `is_active` as a text input and allowed arbitrary text.

## Fix
Changed Warehouse Resource field type from `Text` to `Boolean`:

```php
use Modules\Core\Fields\Boolean;

Boolean::make('is_active', __('warehouse::warehouse.fields.is_active'))
    ->rules(['nullable', 'boolean'])
    ->creationRules('nullable', 'boolean')
    ->updateRules('nullable', 'boolean');
```

Added a model-level default:

```php
protected $attributes = [
    'is_active' => true,
];
```

The existing Eloquent cast remains:

```php
protected $casts = [
    'is_active' => 'boolean',
];
```

## Files changed

```text
modules/Warehouse/app/Resources/Warehouse.php
modules/Warehouse/app/Models/Warehouse.php
docs/ai/02-domains/warehouse.md
docs/ai/03-architecture/resource-field-type-mapping.md
docs/ai/04-docops/history/2026-06-19-warehouse-boolean-field-fix.md
docs/ai/04-docops/task_state.json
docs/ai/05-rag/module-manifest/warehouse.json
```

## Builder lesson
Module Builder must not generate Resource fields independently from database schema. It must map database type, model cast, field UI, and validation as one atomic field definition.

## Validation commands

```bash
docker compose exec app php artisan optimize:clear
sudo rm -f public/hot
docker compose exec node npm run build
docker compose restart app nginx
```

Then create a Warehouse and confirm the payload sends `is_active` as boolean-like value, not free text.

## Next step
After this fix, continue module completion with:

```text
1. Permission behavior
2. Custom Fields compatibility
3. Import/Export validation
4. Notes/Documents/Activities integration
5. Audit/Timeline
```
