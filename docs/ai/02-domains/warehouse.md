# Warehouse Domain — MVP Entity Module

## Status
Implementation in progress. Backend resource registration is present; the remaining blocker was frontend route registration/bundling.

## Current verified state — 2026-06-17

The uploaded project was inspected after the `/warehouses` page still returned 404.

Verified files exist:

```text
modules/Warehouse/module.json
modules/Warehouse/composer.json
modules/Warehouse/app/Models/Warehouse.php
modules/Warehouse/app/Policies/WarehousePolicy.php
modules/Warehouse/app/Providers/WarehouseServiceProvider.php
modules/Warehouse/app/Providers/RouteServiceProvider.php
modules/Warehouse/app/Resources/Warehouse.php
modules/Warehouse/app/Resources/WarehouseTable.php
modules/Warehouse/database/migrations/2026_06_12_184218_create_warehouses_table.php
modules/Warehouse/lang/en/warehouse.php
modules/Warehouse/resources/js/app.js
modules/Warehouse/resources/js/routes.js
modules/Warehouse/resources/js/views/WarehousesIndex.vue
modules/Warehouse/resources/js/views/WarehousesCreate.vue
modules/Warehouse/resources/js/views/WarehousesView.vue
modules/Warehouse/routes/api.php
modules/Warehouse/routes/web.php
```

Known backend checkpoint from the previous implementation session:

```php
\Modules\Core\Facades\Innoclapps::resourceByName('warehouses')
// returns Modules\Warehouse\Resources\Warehouse

\Modules\Core\Facades\Innoclapps::resourceByName('warehouse')
// returns null
```

Meaning: the correct resource name is `warehouses` and the backend resource was registered.

## Purpose
Warehouse is the first manual test module for validating the ErpSmart entity lifecycle. It is not the final inventory system. Its job is to prove that a new business entity can be introduced through the Concord/ErpSmart module-resource architecture and later become a template for an internal Builder.

## MVP scope
Warehouse MVP includes:

```text
Module registration
Database table: warehouses
Model: Warehouse
Policy: WarehousePolicy
Resource: Warehouse
Table: WarehouseTable
Backend resource engine compatibility
Frontend index/create/view routes
Main menu item
Translations
Build validation
```

MVP excludes for now:

```text
stock movements
inventory valuation
product quantity logic
purchase/sales integration
workflow triggers/actions
advanced activity timeline
documents/emails integration
SaaS tenant automation
```

## Root cause of the 404 after the last action

The Warehouse frontend was created under:

```text
modules/Warehouse/resources/js/app.js
modules/Warehouse/resources/js/routes.js
```

but it was not imported into the root frontend entry:

```text
resources/js/app.js
```

Existing core modules such as Users, Activities, Contacts, Deals, Documents, MailClient, Notes, WebForms and ThemeStyle are imported from the root app bundle. Warehouse was missing from that list.

Without this import, Vue Router never receives the `/warehouses` route, so the Core router falls through to the SPA 404 page even if the backend Resource exists.

Required fix:

```js
// resources/js/app.js
import '@/Warehouse/app.js'
```

For the current MVP, Warehouse should use the root app bundle. Do not depend on separate module Vite output until the module packaging pipeline is finalized.

## Frontend asset strategy

### Current MVP strategy

```text
Root bundle strategy:
resources/js/app.js imports modules/Warehouse/resources/js/app.js
npm run build builds public/build/manifest.json
```

This matches the built-in Concord/ErpSmart modules.

### Later packaged-module strategy

A separate build path can be used later:

```text
modules/Warehouse/vite.config.js
public/modules/warehouse/build/manifest.json
Innoclapps::vite(...)
```

Do not mix both strategies unless duplicate route registration is intentionally handled.

## Backend architecture

`WarehouseServiceProvider` must keep:

```php
protected array $resources = [
    \Modules\Warehouse\Resources\Warehouse::class,
];
```

and must register:

```php
$this->app->register(RouteServiceProvider::class);
```

The project history showed that in this local codebase, explicit `registerResources()` inside `register()` made `resourceByName('warehouses')` available. Keep it until the real `ModuleServiceProvider` behavior is rechecked in the full non-truncated source.

## Resource behavior

Warehouse Resource is builder-aware but intentionally simple.

Current behavior:

```text
Resource name: warehouses
Model: Modules\Warehouse\Models\Warehouse
Title column: name
Icon: ArchiveBox
Menu path: /warehouses
Table: WarehouseTable
Default table view: all-warehouses
Fields: id, name, code, description, is_active, created_at, updated_at
Filters: name, code, created_at, updated_at
Permissions: common permissions registered
```

Important rule:
Do not copy Deal contracts blindly. Deal is complex because it is billable, importable, mediable, workflow-heavy and pipeline-based. Warehouse MVP should stay minimal until the route/table/form cycle works.

## Database fields

```text
id
name
code nullable unique candidate
description nullable
is_active boolean default true
created_at
updated_at
```

Optional later:

```text
tenant_id
manager_id
company_id
address fields
```

## Builder relevance

Warehouse is the prototype for the future internal Builder.

A Builder-generated entity must eventually produce the same lifecycle artifacts:

```text
module.json
composer.json
ServiceProvider
RouteServiceProvider
migration
model
policy
resource
table
translation file
frontend app.js
frontend routes.js
frontend views
root app import or module vite manifest
SmartDocs domain entry
history entry
validation checklist
```

## Validation commands

After applying the current MVP fix:

```bash
docker compose exec app composer dump-autoload
docker compose exec app php artisan optimize:clear
sudo rm -f public/hot
docker compose exec node npm run build
docker compose restart app nginx
```

Then test backend registration:

```bash
docker compose exec app php artisan tinker
```

```php
\Modules\Core\Facades\Innoclapps::resourceByName('warehouses')::class;
```

Expected:

```text
Modules\Warehouse\Resources\Warehouse
```

Then test frontend:

```text
http://localhost:8080/warehouses
```

Expected MVP smoke page:

```text
Warehouses Index
```

If still 404:

```bash
docker compose exec app sh -c "grep -n \"Warehouse/app\" resources/js/app.js"
docker compose exec app ls -la public/build/manifest.json
docker compose exec app php artisan optimize:clear
docker compose logs node --tail=100
```

## Update rules

Every time Warehouse implementation changes, update:

```text
docs/ai/02-domains/warehouse.md
docs/ai/04-docops/history/YYYY-MM-DD-warehouse-*.md
```
