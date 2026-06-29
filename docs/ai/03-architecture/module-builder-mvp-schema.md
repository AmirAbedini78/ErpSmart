# ERPSMART Module Builder MVP Schema And Dry-Run Foundation

Status: design foundation
Date: 2026-06-30

Warehouse is the MVP canonical module template. The first Module Builder step is a schema and dry-run contract only. It must validate a module definition and print planned files; it must not create module files yet.

## Source Pattern

Use Warehouse and first-party modules as evidence:

- `modules/Warehouse/app/Resources/Warehouse.php`
- `modules/Warehouse/app/Models/Warehouse.php`
- `modules/Warehouse/app/Http/Resources/WarehouseResource.php`
- `modules/Warehouse/app/Providers/WarehouseServiceProvider.php`
- `modules/Warehouse/resources/js/views/WarehousesView.vue`
- `patches/verify_warehouse_standard_detail_page_contract.php`
- `docs/ai/05-rag/exclusions/warehouse-canonical-template-exclusions.json`

Do not use Warehouse `.bak-*` files, generated build output, dependency folders, cache files, or superseded attempts as source evidence.

## Schema Artifact

The formal MVP schema is:

- `docs/ai/05-rag/contracts/module-builder-mvp-schema.json`

It covers:

- module name, namespace, labels, table, route/resource name, and icon
- Resource class metadata
- fields, validation rules, defaults, visibility, and table/index behavior
- StandardDetailPage panels/tabs
- capability flags
- permissions
- frontend files
- verifier generation

## Capability Flags

MVP flags:

- `tableable`
- `customFields`
- `uniqueCustomFields`
- `importable`
- `exportable`
- `cloneable`
- `mediable`
- `notes`
- `activities`
- `activityComments`
- `activityAssociations`
- `globalSearch`
- `quickCreate`
- `bulkDelete`
- `softDeletes`
- `timeline`

Documents, Calls, Emails/MailClient, workflow triggers, dashboards, SaaS automation, and pipeline/kanban resources are intentionally out of scope for the MVP schema.

## Dry-Run Command Design

Preferred eventual command:

```bash
php artisan erpsmart:make-module --definition=module-definition.json --dry-run
```

Dry-run behavior:

- load and parse the JSON definition
- validate required keys and basic structural constraints
- normalize module/entity names
- print selected capabilities
- print unsupported capabilities as warnings
- print backend files that would be generated
- print frontend files that would be generated
- print docs/verifier files that would be generated
- perform no writes

No Artisan command was implemented in this phase. Registering a command safely requires choosing a runtime registration location; that is deferred to avoid broad Core or application changes before the schema and dry-run output contract are verified.

## Dry-Run Output Shape

Example output sections:

```text
ERPSMART Module Builder Dry Run

Definition: module-definition.json
Module: Inventory
Entity: Item / Items
Resource: items
Table: items

Capabilities:
- tableable: true
- importable: true
- exportable: true
- notes: true
- activities: true

Backend files:
- modules/Inventory/module.json
- modules/Inventory/app/Providers/InventoryServiceProvider.php
- modules/Inventory/app/Resources/Item.php

Frontend files:
- modules/Inventory/resources/js/routes.js
- modules/Inventory/resources/js/views/ItemsView.vue

Docs/verifier files:
- patches/verify_inventory_item_contract.php
- docs/ai/04-docops/history/YYYY-MM-DD-inventory-item-generated.md

Warnings:
- timeline requested but out of MVP implementation scope

Writes performed: 0
```

## Generated File Plan

Backend:

- `modules/{Module}/module.json`
- `modules/{Module}/bootstrap/module.php`
- `modules/{Module}/app/Providers/{Module}ServiceProvider.php`
- `modules/{Module}/app/Providers/RouteServiceProvider.php`
- `modules/{Module}/app/Models/{Entity}.php`
- `modules/{Module}/app/Resources/{Entity}.php`
- `modules/{Module}/app/Resources/{Entity}Table.php`
- `modules/{Module}/app/Http/Resources/{Entity}Resource.php`
- `modules/{Module}/app/Policies/{Entity}Policy.php`
- `modules/{Module}/database/migrations/create_{table}_table.php`
- `modules/{Module}/routes/api.php`
- `modules/{Module}/routes/web.php`

Frontend:

- `modules/{Module}/resources/js/app.js`
- `modules/{Module}/resources/js/routes.js`
- `modules/{Module}/resources/js/views/{Entities}Index.vue`
- `modules/{Module}/resources/js/views/{Entities}Create.vue`
- `modules/{Module}/resources/js/views/{Entities}Edit.vue`
- `modules/{Module}/resources/js/views/{Entities}View.vue`
- `modules/{Module}/resources/js/components/{Entity}FloatingModal.vue`

Docs/verifier:

- `patches/verify_{module}_{entity}_contract.php`
- `docs/ai/04-docops/history/YYYY-MM-DD-{module}-{entity}-generated.md`
- optional RAG manifest entries after generation is proven

## Warehouse Patterns To Copy

- Resource extends `Modules\Core\Resource\Resource`
- JSON Resource extends `Modules\Core\Resource\JsonResource`
- JSON Resource calls `withCommonData()`
- detail page metadata uses `Panel` and `Tab`
- detail frontend consumes `resourceInformation.value.detailPage`
- media renders through `resource-media-panel`
- notes and activities are optional tab capabilities
- floating edit uses Core floating modal contract
- verifier checks source contracts and forbidden path changes

## MVP Out Of Scope

- write-capable generation without dry-run review
- Core changes
- runtime module source changes during schema work
- Documents, Calls, Emails, MailClient
- Timeline UI generation
- workflow triggers
- dashboard cards
- SaaS/tenant behavior
- broad relation designer
