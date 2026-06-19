# Warehouse Domain — First Builder-Aware Resource Module

## Status
MVP is now beyond smoke-test. `/warehouses` is registered in the Vue router and the module has been upgraded to use the Core Resource UI pattern for index/create/edit/detail.

## Purpose
Warehouse is the first manual test module for validating the ErpSmart entity lifecycle. It is not the final inventory system yet. Its job is to prove that a new business entity can be introduced through the Concord/ErpSmart module/resource architecture and later become a template for the internal Module Builder.

This module must therefore be treated as both:

```text
1. A real business module: warehouse master data.
2. A reference template for future Builder-generated modules.
```

## Current MVP scope
Implemented or expected in this stage:

```text
Module registration
Database table: warehouses
Model: Modules\Warehouse\Models\Warehouse
Policy: Modules\Warehouse\Policies\WarehousePolicy
Resource: Modules\Warehouse\Resources\Warehouse
Table: Modules\Warehouse\Resources\WarehouseTable
Backend Resource route compatibility via WithResourceRoutes
Frontend route registration via modules/Warehouse/resources/js/app.js
Root frontend import via resources/js/app.js
Resource index table via ResourceTable
Create slideover via create-fields endpoint
Edit slideover via update-fields endpoint
Detail page via DetailFields
Resource export modal hook
Import route hook, if resource authorization enables it
Translations
Build validation
```

Still excluded from this MVP:

```text
stock movements
inventory balances
inventory valuation
products/items
warehouse locations/bins
purchase/sales integration
workflow triggers/actions
documents/notes/activity timeline integration
advanced dashboard metrics
```

## Business fields
Current table fields:

```text
id
name
code nullable unique candidate
description nullable
is_active boolean default true
created_at
updated_at
```

Future warehouse master fields:

```text
manager_id
is_default
address
phone
sort_order
warehouse_type
branch_id/company_id if multi-company is introduced
```

## Runtime table naming
The migration table name is written as:

```text
warehouses
```

At runtime the application may use the configured database prefix, so SQL/debug output can show:

```text
tbl_warehouses
```

Do not manually use `tbl_warehouses` in model `$table`; keep the model table as `warehouses` so Laravel can apply the configured prefix.

## File map

```text
modules/Warehouse/module.json
modules/Warehouse/composer.json
modules/Warehouse/app/Providers/WarehouseServiceProvider.php
modules/Warehouse/app/Providers/RouteServiceProvider.php
modules/Warehouse/routes/api.php
modules/Warehouse/routes/web.php
modules/Warehouse/app/Models/Warehouse.php
modules/Warehouse/app/Policies/WarehousePolicy.php
modules/Warehouse/app/Resources/Warehouse.php
modules/Warehouse/app/Resources/WarehouseTable.php
modules/Warehouse/database/migrations/2026_06_12_184218_create_warehouses_table.php
modules/Warehouse/lang/en/warehouse.php
modules/Warehouse/resources/js/app.js
modules/Warehouse/resources/js/routes.js
modules/Warehouse/resources/js/views/WarehousesIndex.vue
modules/Warehouse/resources/js/views/WarehousesCreate.vue
modules/Warehouse/resources/js/views/WarehousesEdit.vue
modules/Warehouse/resources/js/views/WarehousesView.vue
resources/js/app.js
```

## Backend lifecycle

1. `modules_statuses.json` enables the module.
2. `module.json` registers `Modules\\Warehouse\\Providers\\WarehouseServiceProvider`.
3. `WarehouseServiceProvider` registers `Warehouse` in `protected array $resources`.
4. `register()` calls `$this->registerResources()` and registers `RouteServiceProvider`.
5. `Warehouse` implements `Tableable` and `WithResourceRoutes`.
6. Core Resource API exposes table, fields, create, update, retrieve, delete, actions, import/export where available.

Critical check:

```php
\Modules\Core\Facades\Innoclapps::resourceByName('warehouses')::class;
```

Expected:

```text
Modules\Warehouse\Resources\Warehouse
```

`resourceByName('warehouse')` is expected to be `null`; the resource name is plural.

## Frontend lifecycle

1. Root app imports `@/Warehouse/app.js` from `resources/js/app.js`.
2. `modules/Warehouse/resources/js/app.js` registers routes during `Innoclapps.booting`.
3. `routes.js` adds:

```text
/warehouses              -> warehouse-index
/warehouses/create       -> create-warehouse
/warehouses/:id          -> view-warehouse
/warehouses/:id/edit     -> edit-warehouse
```

4. `WarehousesIndex.vue` uses `ResourceTable` and `ResourceExport`.
5. `WarehousesCreate.vue` uses `getCreateFields()` and `createResource()`.
6. `WarehousesEdit.vue` uses `getUpdateFields()`, `retrieveResource()`, and `updateResource()`.
7. `WarehousesView.vue` uses `useResource()`, `getDetailFields()`, and `DetailFields`.

## 404 root cause fixed
The route `/warehouses` previously returned a frontend 404 because the module JS entry existed but was not imported into the root frontend bundle.

Fix:

```js
import '@/Warehouse/app.js'
```

must exist in:

```text
resources/js/app.js
```

This is a critical Builder rule: creating a module is not complete unless its frontend registration path is generated and included in the application bundle.

## Builder implications
The future Module Builder must generate all of these layers atomically:

```text
module.json
ServiceProvider with $resources
RouteServiceProvider
migration
model
policy
resource
table
translations
frontend app.js
frontend routes.js
index/create/edit/detail views
root frontend registration or dynamic module manifest registration
SmartDocs domain file
RAG manifest JSON
history entry
```

A module that only has migration/model/resource but is missing frontend bundle registration will look valid in backend checks but still fail in Vue navigation.

## Validation checklist

```bash
# backend resource check
docker compose exec app php artisan tinker
```

```php
\Modules\Core\Facades\Innoclapps::resourceByName('warehouses')::class;
```

```bash
# frontend registration check
docker compose exec app sh -c "grep -n \"Warehouse/app\" resources/js/app.js"

# build
docker compose exec app php artisan optimize:clear
sudo rm -f public/hot
docker compose exec node npm run build
docker compose restart app nginx
```

Browser checks:

```text
http://localhost:8080/warehouses
http://localhost:8080/warehouses/create
http://localhost:8080/warehouses/{id}
http://localhost:8080/warehouses/{id}/edit
```

## Next development phases

```text
1. Verify ResourceTable, create, edit, detail view in browser.
2. Verify API endpoints for warehouses, table, create-fields, update-fields, detail-fields.
3. Replace temporary permissive policy with real permission behavior if needed.
4. Confirm custom fields compatibility.
5. Confirm import/export behavior.
6. Add notes/documents/activities/audit only after master data resource is stable.
7. Add Warehouse Locations as the next child entity.
8. Add Products/Items.
9. Add Stock Movements and Balances.
```
