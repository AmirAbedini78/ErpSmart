# Resource Permission Policy Pattern

## Purpose
This document records the permission pattern used while upgrading `Warehouse` from an MVP resource to a Builder-ready first-class resource.

The goal is not only to secure Warehouse, but to teach the future Module Builder how to generate authorization code consistently with Core Resource behavior.

## Concord/ErpSmart permission layers
A business Resource uses multiple authorization layers:

```text
1. Resource registers permission names via registerPermissions().
2. Core/Spatie stores permissions and exposes them in Settings -> Roles.
3. Laravel policies authorize API actions such as viewAny/create/update/delete/export.
4. Super admins bypass restrictions through the global Gate::before rule in UsersServiceProvider.
5. Frontend hides/shows actions based on permissions and API authorization responses.
```

## Standard Resource permission names
For builder-generated master data resources, use these baseline permission names:

```text
view all {resource_plural}
create {resource_plural}
edit all {resource_plural}
delete any {resource_singular}
bulk delete {resource_plural}
export {resource_plural}
import {resource_plural}
```

Examples for Warehouse:

```text
view all warehouses
create warehouses
edit all warehouses
delete any warehouse
bulk delete warehouses
export warehouses
import warehouses
```

Some Core/common permissions may register additional variants such as `own` or `team` permissions. The Builder must not blindly enable those variants unless the generated model has the required ownership/team columns and query scopes.

## Ownership rule
Warehouse is currently global master data. It has no `user_id`, `owner_id`, `team_id`, `created_by` ownership policy, or visibility-group scope.

Therefore the Warehouse policy only treats all/global permissions as sufficient:

```text
view all warehouses
edit all warehouses
delete any warehouse
```

Do not treat `view own warehouses` or `view team warehouses` as valid until the schema and query layer support ownership.

## Policy generation rule for Builder
When the Builder generates a module, it must select one of these policy modes:

```text
master_data_global
owned_by_user
team_visible
visibility_group_based
super_admin_only
custom_policy
```

For `master_data_global`, generate policy checks against global permissions only.

For `owned_by_user`, the Builder must also generate:

```text
owner column
model relationship
query scopes/table filters
view/update/delete own policy branches
SmartDocs ownership declaration
RAG manifest ownership mode
```

For `team_visible`, it must also generate team relation/scopes and use `managesAnyTeamsOf()` or the appropriate project-level team method.

## Validation checklist
After a Resource policy upgrade:

```text
php artisan optimize:clear
php artisan permissions:create-missing if available in this installation
GET /api/permissions should include the new Resource permissions
super admin can create/edit/delete/export
non-super-admin without permissions receives 403 for protected actions
role with view/create/edit/export permissions can perform only those actions
```

## Warehouse current policy mode

```json
{
  "module": "Warehouse",
  "policy_mode": "master_data_global",
  "ownership_columns": [],
  "supported_permissions": [
    "view all warehouses",
    "create warehouses",
    "edit all warehouses",
    "delete any warehouse",
    "bulk delete warehouses",
    "export warehouses",
    "import warehouses"
  ]
}
```

## 2026-06-20 Permission UI and data-exposure fix

The first strict Warehouse policy revealed two integration issues:

1. `create warehouses` was checked by the policy but was not registered in the role permission UI.
2. A non-super-admin user could still open the Warehouse table and see rows because the table query was not filtered when the user lacked `view all warehouses`.

Fix applied:

```text
modules/Warehouse/app/Resources/Warehouse.php
modules/Warehouse/app/Policies/WarehousePolicy.php
modules/Warehouse/app/Models/Warehouse.php
modules/Warehouse/lang/en/warehouse.php
```

Warehouse now registers its own master-data permission group instead of using only `registerCommonPermissions()`. This avoids misleading `own/team` permissions for a model that has no owner/team columns yet.

Current permission matrix:

```text
view all warehouses       -> can list/view Warehouse records
create warehouses         -> can create Warehouse records; also controls Core import visibility for Importable resources
edit all warehouses       -> can update Warehouse records
delete any warehouse      -> can delete one Warehouse record
bulk delete warehouses    -> can run bulk delete when also allowed to delete each selected record
export warehouses         -> can export Warehouse records
import warehouses         -> reserved explicit capability for future Warehouse-specific import authorization
```

Important Builder rule: when a generated module is `master_data_global`, do not blindly generate `own/team` role UI entries unless the Builder also generates ownership/team columns, criteria classes, filters, and policy branches.

A table-level guard was also added. If the logged-in user lacks `view all warehouses`, the Warehouse table query is forced to return zero rows. This prevents rows from being visible through ResourceTable even when a frontend route/menu is reachable.

