# 2026-06-19 — Warehouse Permission Policy Step

## Context
Warehouse create/edit/table was validated successfully after fixing the `is_active` field type. The next step was to move Warehouse away from an always-true MVP policy and align it with the Core Resource permission model.

## Changed
Updated:

```text
modules/Warehouse/app/Policies/WarehousePolicy.php
```

Added:

```text
docs/ai/03-architecture/resource-permission-policy.md
```

Updated:

```text
docs/ai/02-domains/warehouse.md
docs/ai/05-rag/module-manifest/warehouse.json
docs/ai/04-docops/task_state.json
```

## Policy behavior
Warehouse is currently global master data, not owned/team-scoped data. Therefore policy checks are based on global permissions only:

```text
view all warehouses
create warehouses
edit all warehouses
delete any warehouse
bulk delete warehouses
export warehouses
import warehouses
```

`view own warehouses`, `edit own warehouses`, or team permissions are intentionally not treated as sufficient because Warehouse has no ownership/team columns yet.

## Builder lesson
The future Module Builder must generate permission policy code from a declared policy mode.

Current Warehouse mode:

```text
master_data_global
```

This means:

```text
no owner column
no team relation
no own/team policy branch
global permissions only
```

If a future module is owned by a user, the Builder must generate owner columns, relations, scopes, policy branches, and RAG metadata together.

## Validation
Run:

```bash
php artisan optimize:clear
```

Then check:

```php
$resource = \Modules\Core\Facades\Innoclapps::resourceByName('warehouses');
$resource::label();
```

Manual API/Browser validation:

```text
super admin can still list/create/edit/delete warehouses
non-super-admin without role permissions should receive 403 on protected Resource actions
role with Warehouse permissions should be able to perform the assigned actions only
```

If this installation exposes a permissions sync command, run it before non-super-admin role testing.
