# 2026-06-19 — Warehouse Resource Capability Contracts

## Context
Warehouse create/edit/table were validated in the browser after the boolean `is_active` fix. The next step is to move Warehouse from a basic CRUD Resource toward a first-class Core Resource that behaves like mature modules.

## Change summary
Enabled these Resource capability contracts on `Modules\Warehouse\Resources\Warehouse`:

```text
AcceptsCustomFields
AcceptsUniqueCustomFields
Exportable
Importable
Tableable
WithResourceRoutes
```

Added import tracking schema support:

```text
modules/Warehouse/database/migrations/2026_06_19_160500_add_import_id_to_warehouses_table.php
warehouses.import_id nullable indexed
```

Updated model support:

```text
fillable: import_id
casts: import_id => integer
```

## Why this matters
Core controllers protect import/export/custom-field flows with marker contracts:

```text
ExportController     -> aborts unless Resource instanceof Exportable
ImportController     -> aborts unless Resource instanceof Importable
CustomFieldRequest   -> lists resources instanceof AcceptsCustomFields
```

Therefore, showing import/export UI is not enough. The backend Resource must explicitly opt into these capabilities.

## Builder rule learned
A future Module Builder must generate capability contracts with their technical prerequisites:

```text
Importable -> import_id column + model fillable/cast + API smoke tests
Exportable -> export-fields/export endpoint smoke tests
AcceptsCustomFields -> custom-field eligibility + field ID collision checks
AcceptsUniqueCustomFields -> unique custom field support where field type allows it
```

Do not add contracts blindly. Each contract has runtime effects and may reveal missing schema/model/policy behavior.

## Validation commands

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize:clear
```

Tinker checks:

```php
$resource = \Modules\Core\Facades\Innoclapps::resourceByName('warehouses');
$resource instanceof \Modules\Core\Contracts\Resources\Exportable;
$resource instanceof \Modules\Core\Contracts\Resources\Importable;
$resource instanceof \Modules\Core\Contracts\Resources\AcceptsCustomFields;
$resource instanceof \Modules\Core\Contracts\Resources\AcceptsUniqueCustomFields;
\Illuminate\Support\Facades\Schema::hasColumn('warehouses', 'import_id');
```

Browser/API checks after login:

```text
GET /api/warehouses/export-fields
GET /api/warehouses/import/sample
GET /api/warehouses/create-fields
GET /api/warehouses/table
```

## Next step
After these endpoints are confirmed, implement stricter Warehouse permission behavior and then start Notes/Documents/Activities/Audit integration.
