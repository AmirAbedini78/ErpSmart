# History — Warehouse MVP 404 analysis and SmartDocs update

## Date
2026-06-17

## Change type
Bug analysis + minimal code patch + SmartDocs/RAG update.

## Context
The Warehouse module had been created and the backend Resource had already been confirmed in Tinker:

```php
Innoclapps::resourceByName('warehouses')
// Modules\Warehouse\Resources\Warehouse
```

However, opening:

```text
http://localhost:8080/warehouses
```

still returned a 404.

## Findings
The uploaded project was extracted and inspected. Warehouse backend files were present, but the root frontend entry did not import the Warehouse module app file.

Missing line:

```js
import '@/Warehouse/app.js'
```

File:

```text
resources/js/app.js
```

Because the project uses Vue Router and root-bundled module app files, `modules/Warehouse/resources/js/routes.js` was never executed. Therefore the `/warehouses` route was never added to the SPA router.

## Code changes applied in the analyzed copy

```text
resources/js/app.js
modules/Warehouse/app/Providers/WarehouseServiceProvider.php
modules/Warehouse/resources/js/views/WarehousesIndex.vue
modules/Warehouse/resources/js/views/WarehousesCreate.vue
modules/Warehouse/resources/js/views/WarehousesView.vue
```

### Frontend registration
Added:

```js
import '@/Warehouse/app.js'
```

### Provider strategy
Warehouse frontend is now documented as root-bundled for MVP. Separate `Innoclapps::vite()` output is deferred until the module packaging pipeline is finalized.

### Smoke views
Warehouse Vue views now use `MainLayout` and simple smoke-test content so the route can be visually verified before replacing them with generic Resource table/form components.

## Why this matters for Builder
A future Builder cannot generate only backend files. It must also generate/modify the frontend registration point or produce a valid module Vite manifest that is loaded by the shell.

Required Builder artifact for current root-bundled strategy:

```text
resources/js/app.js import line
```

or for future packaged-module strategy:

```text
modules/{Module}/vite.config.js
public/modules/{module}/build/manifest.json
Innoclapps::vite(...) registration
layout with viteOutput()
```

## Validation checklist

```bash
docker compose exec app composer dump-autoload
docker compose exec app php artisan optimize:clear
sudo rm -f public/hot
docker compose exec node npm run build
docker compose restart app nginx
```

Then:

```text
http://localhost:8080/warehouses
```

Expected:

```text
Warehouses Index
```

## Follow-up
Replace smoke-test views with the generic Core resource UI or create a small Warehouse-specific resource table/form implementation. Do not add inventory/business logic until the MVP route/table/create/detail loop is stable.
