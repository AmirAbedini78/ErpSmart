# Warehouse Domain — Planned Entity Module

## Status
Planned MVP. Do not treat this as completed implementation.

## Purpose
Warehouse is the first manual test module for validating the ErpSmart entity lifecycle. It is not the final inventory system. Its job is to prove that a new business entity can be introduced through the Concord module/resource architecture and later become a template for an internal Builder.

## MVP scope
Warehouse MVP includes:

```text
Module registration
Database table: warehouses
Model: Warehouse
Resource: Warehouse
Table: WarehouseTable
Backend route/resource engine compatibility
Frontend index/create/view routes
Main menu item
Basic settings entry if needed
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

## Target file map

```text
modules/Warehouse/module.json
modules/Warehouse/composer.json
modules/Warehouse/app/Providers/WarehouseServiceProvider.php
modules/Warehouse/app/Providers/RouteServiceProvider.php
modules/Warehouse/routes/api.php
modules/Warehouse/app/Models/Warehouse.php
modules/Warehouse/app/Policies/WarehousePolicy.php
modules/Warehouse/app/Resources/Warehouse.php
modules/Warehouse/app/Resources/WarehouseTable.php
modules/Warehouse/app/Http/Resources/WarehouseResource.php
modules/Warehouse/database/migrations/YYYY_MM_DD_HHMMSS_create_warehouses_table.php
modules/Warehouse/database/factories/WarehouseFactory.php
modules/Warehouse/lang/en/warehouse.php
modules/Warehouse/resources/js/app.js
modules/Warehouse/resources/js/routes.js
modules/Warehouse/resources/js/views/WarehousesIndex.vue
modules/Warehouse/resources/js/views/WarehousesCreate.vue
modules/Warehouse/resources/js/views/WarehousesView.vue
```

## Minimal database fields
For MVP:

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

## Resource behavior
Warehouse Resource should be simple but builder-aware.

Recommended MVP capabilities:

```text
Tableable
WithResourceRoutes
AcceptsCustomFields only if custom fields are confirmed to work for new resources
```

Avoid copying `Deal` contracts blindly. Deal is complex because it is billable, importable, mediable, workflow-heavy, and pipeline-based.

## Frontend behavior
The MVP should use existing Core resource UI patterns as much as possible. If generic Resource views exist in Core, prefer them. If not, create thin Warehouse views that call resource endpoints and render the existing table/form components.

## Manual build guide
Use:

```text
docs/ai/04-docops/expansion/manual-build-warehouse-module-step-by-step.md
```

## Update rules
Every time Warehouse implementation changes, update:

```text
docs/ai/02-domains/warehouse.md
docs/ai/04-docops/history/YYYY-MM-warehouse-*.md
```
