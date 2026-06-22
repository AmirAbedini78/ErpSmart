# 2026-06-20 — Warehouse permission/action cleanup

## Context
After the Warehouse permission UI fix, manual validation showed:

- The role UI still showed duplicate generic `Export` entries.
- A normal user did not see all useful Warehouse columns in the data table.
- Delete/clone row actions were not visible in the Warehouse table.

## Root cause
The Warehouse module went through several early MVP permission iterations. Some permission records were persisted in the database even after the source code changed. Since Spatie permissions are stored persistently, removing/changing permission registration code does not automatically remove stale rows.

Table actions were also not explicitly registered. Policy authorization alone does not create row actions in the UI.

## Changes

```text
modules/Warehouse/app/Resources/Warehouse.php
modules/Warehouse/database/migrations/2026_06_20_180000_cleanup_warehouse_permissions.php
docs/ai/02-domains/warehouse.md
docs/ai/03-architecture/resource-permission-policy.md
docs/ai/04-docops/task_state.json
docs/ai/05-rag/module-manifest/warehouse.json
```

## Technical notes

- Added `Cloneable` to Warehouse Resource.
- Added `CloneAction` and `DeleteAction` to `Warehouse::actions()`.
- Implemented `Warehouse::clone()` with unique copy name/code generation.
- Added a cleanup migration that removes stale Warehouse-related permission records not in the canonical permission matrix.
- Made `description` visible on index/table by default.

## Canonical Warehouse permission matrix

```text
view all warehouses
create warehouses
edit all warehouses
delete any warehouse
bulk delete warehouses
export warehouses
import warehouses
```

## Validation

```text
php artisan migrate --force
php artisan optimize:clear
php artisan permission:cache-reset
```

Then validate:

- Warehouse role UI has no duplicate generic Export rows.
- Normal role with view permission sees Warehouse records and useful columns.
- Clone action appears when create permission is granted.
- Delete action appears when delete permission is granted.
- User without view permission sees no Warehouse rows.

## Builder lesson
Permission generation is stateful because DB permission rows survive code changes. A module builder must generate permission cleanup/sync logic whenever permission names or capability contracts change.
