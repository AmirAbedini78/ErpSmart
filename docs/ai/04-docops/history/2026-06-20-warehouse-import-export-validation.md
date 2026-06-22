# 2026-06-20 — Warehouse import/export validation phase

## Context

After Warehouse CRUD, permissions, custom fields, clone/delete actions, and offline Vue runtime were stabilized, the next module-completion phase is Import/Export end-to-end validation.

## Changes

```text
modules/Warehouse/app/Models/Warehouse.php
modules/Warehouse/lang/en/warehouse.php
docs/ai/03-architecture/resource-import-export.md
docs/ai/04-docops/checklists/warehouse-import-export-validation.md
docs/ai/04-docops/samples/warehouses-import-sample.csv
docs/ai/05-rag/module-manifest/warehouse.json
```

## Technical update

The Warehouse model now normalizes `is_active` values in a model mutator. This is required because Resource forms send booleans, but CSV import can submit strings. Without this guard, import can fail with MySQL errors such as `Incorrect integer value` for boolean columns.

## Known issue carried into this phase

The role UI may still show multiple generic `Export` rows. This is not treated as a blocker for import/export validation because Warehouse operations are functional. It must be inspected through the Core permission registry:

```php
collect(\Modules\Core\Facades\Permissions::groups()['warehouses']['views'] ?? [])->map(fn ($view) => [
    'view' => $view['view'],
    'as' => $view['as'],
    'keys' => $view['keys'],
    'permissions' => $view['permissions'],
]);
```

## Validation focus

- `/api/warehouses/export-fields` returns field list.
- `/api/warehouses/export` downloads CSV/XLS/XLSX.
- `/api/warehouses/import/sample` downloads a sample CSV.
- UI import upload maps Name, Code, Description, Active.
- Imported rows receive `import_id`.
- Revert deletes records created by the import.
