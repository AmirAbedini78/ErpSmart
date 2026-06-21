# 2026-06-20 — Warehouse Permission UI and Table Exposure Fix

## Context
After the first policy hardening step, a non-super-admin user could see Warehouse data in the table, but create/update operations failed. The role permissions UI also did not expose a usable Warehouse permission set for granting the new user the required access.

## Root cause
The first policy step checked permissions such as `create warehouses`, but the Resource still only called `registerCommonPermissions()`. The common provider covers view/edit/delete/bulk-delete style resource permissions, but it does not provide a Warehouse-specific `create warehouses` control.

Additionally, Warehouse had no owner/team schema. Using the generic own/team UI entries would be misleading and unsafe for this master data module.

## Changed
Updated:

```text
modules/Warehouse/app/Resources/Warehouse.php
modules/Warehouse/app/Policies/WarehousePolicy.php
modules/Warehouse/app/Models/Warehouse.php
modules/Warehouse/lang/en/warehouse.php
```

## Permission matrix

```text
view all warehouses
create warehouses
edit all warehouses
delete any warehouse
bulk delete warehouses
export warehouses
import warehouses
```

## Table guard
`Warehouse::table()` now forces an empty query when the user lacks `view all warehouses`. This prevents table rows from leaking to non-authorized users even if the frontend route is visible.

## Builder implication
For a generated module, permissions cannot be generated in isolation. The Builder must generate:

```text
policy mode
permission UI registration
policy methods
table/query visibility guard
model traits
translation labels
RAG manifest update
history entry
```

For `master_data_global`, generate global permission names only. Do not generate own/team variants unless the Builder also generates the required ownership/team schema and criteria.
