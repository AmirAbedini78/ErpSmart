# 2026-06-18 — Warehouse Resource UI Step

## Context
Warehouse was already visible at `/warehouses` after fixing frontend registration, but it was still a smoke-test page. The next step was to convert it into a real Resource UI module using existing Core components.

## Changes

```text
resources/js/app.js
modules/Warehouse/resources/js/routes.js
modules/Warehouse/resources/js/views/WarehousesIndex.vue
modules/Warehouse/resources/js/views/WarehousesCreate.vue
modules/Warehouse/resources/js/views/WarehousesEdit.vue
modules/Warehouse/resources/js/views/WarehousesView.vue
modules/Warehouse/lang/en/warehouse.php
docs/ai/02-domains/warehouse.md
docs/ai/03-architecture/frontend-module-registration.md
docs/ai/05-rag/module-manifest/warehouse.json
docs/ai/04-docops/task_state.json
```

## Implementation summary

- `WarehousesIndex.vue` now uses `ResourceTable`, `ResourceExport`, empty state, and nested `RouterView` for create/edit overlays.
- `WarehousesCreate.vue` now uses `getCreateFields()` and `createResource()`.
- `WarehousesEdit.vue` now uses `retrieveResource()`, `getUpdateFields()`, and `updateResource()`.
- `WarehousesView.vue` now uses `useResource()`, `getDetailFields()`, and `DetailFields` to render a real detail page.
- Routes were changed to a Resource-like structure:

```text
/warehouses
/warehouses/create
/warehouses/:id
/warehouses/:id/edit
```

## Builder lesson
The Builder must not generate only backend artifacts. It must generate the full path from backend Resource to frontend router and SmartDocs/RAG metadata.

## Validation commands

```bash
docker compose exec app php artisan optimize:clear
sudo rm -f public/hot
docker compose exec node npm run build
docker compose restart app nginx
```

Browser validation:

```text
/warehouses
/warehouses/create
/warehouses/{id}
/warehouses/{id}/edit
```

## Next step
Verify the Resource UI in the browser. After it is stable, move to permission/custom-field/import-export compatibility checks.
