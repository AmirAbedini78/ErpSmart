# Warehouse Import/Export Validation Checklist

## Before testing

```bash
php artisan optimize:clear
php artisan permission:cache-reset
```

Make sure the role/user has:

```text
View all warehouses
Create warehouses
Export warehouses
Import warehouses
```

## API smoke tests

Use an authenticated browser session or API client. Expected endpoints:

```text
GET  /api/warehouses/export-fields
POST /api/warehouses/export
GET  /api/warehouses/import
GET  /api/warehouses/import/sample
POST /api/warehouses/import/upload
POST /api/warehouses/import/{id}
```

## UI export test

1. Open `/warehouses`.
2. Create at least two Warehouse records.
3. Open the action dropdown.
4. Click Export.
5. Export CSV.
6. Confirm the file contains Name, Code, Description, Active.

## UI import test

1. Open `/warehouses`.
2. Open the action dropdown.
3. Click Import.
4. Download sample CSV.
5. Upload `docs/ai/04-docops/samples/warehouses-import-sample.csv` or a similar file.
6. Confirm mappings:

```text
Name -> name
Code -> code
Description -> description
Active -> is_active
```

7. Run import.
8. Confirm records appear in the table.
9. Check database:

```php
DB::table('warehouses')->whereNotNull('import_id')->count();
```

## Boolean validation

The `Active` column may use:

```text
yes / no
true / false
1 / 0
on / off
```

The model mutator must normalize these values before MySQL receives them.

## Duplicate Export permission diagnosis

If the role UI still shows multiple generic Export rows, run this in tinker:

```php
collect(\Modules\Core\Facades\Permissions::groups()['warehouses']['views'] ?? [])->map(fn ($view) => [
    'view' => $view['view'],
    'as' => $view['as'],
    'keys' => $view['keys'],
    'permissions' => $view['permissions'],
]);
```

Do not delete records by label. Export rows can be Core-generated capability views.
