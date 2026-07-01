# ERPSMART Module Builder Preview Renderer

Status: Phase 1 implementation safety note
Date: 2026-07-01

## Purpose

The preview renderer extends the dry-run-only Module Builder foundation without enabling real module writes.
It validates the module definition, normalizes module/entity names, and renders generated files into a fenced preview tree:

```text
storage/app/module-builder-preview/{Module}/
```

The preview tree preserves the future runtime path layout under the preview root so generated files can be inspected before any write-capable builder exists.

## Supported Commands

Dry-run remains unchanged:

```bash
php artisan erpsmart:make-module --definition=docs/ai/05-rag/examples/warehouse-like-module-definition.json --dry-run
```

Preview mode:

```bash
php artisan erpsmart:make-module --definition=docs/ai/05-rag/examples/warehouse-like-module-definition.json --preview
```

The command refuses to run without `--dry-run` or `--preview`.
The command refuses `--write` because real module generation is intentionally out of scope.

## Preview Output Contract

Preview mode writes only under:

```text
storage/app/module-builder-preview/Inventory/
```

For the Warehouse-like sample definition, the preview includes:

- `modules/Inventory/module.json`
- `modules/Inventory/bootstrap/module.php`
- `modules/Inventory/app/Providers/InventoryServiceProvider.php`
- `modules/Inventory/app/Providers/RouteServiceProvider.php`
- `modules/Inventory/app/Models/Item.php`
- `modules/Inventory/app/Resources/Item.php`
- `modules/Inventory/app/Resources/ItemTable.php`
- `modules/Inventory/app/Http/Resources/ItemResource.php`
- `modules/Inventory/app/Policies/ItemPolicy.php`
- `modules/Inventory/database/migrations/create_items_table.php`
- `modules/Inventory/routes/api.php`
- `modules/Inventory/routes/web.php`
- `modules/Inventory/resources/js/app.js`
- `modules/Inventory/resources/js/routes.js`
- `modules/Inventory/resources/js/views/ItemsIndex.vue`
- `modules/Inventory/resources/js/views/ItemsCreate.vue`
- `modules/Inventory/resources/js/views/ItemsEdit.vue`
- `modules/Inventory/resources/js/views/ItemsView.vue`
- `modules/Inventory/resources/js/components/ItemFloatingModal.vue`
- `patches/verify_inventory_item_contract.php`
- `docs/ai/04-docops/history/YYYY-MM-DD-inventory-item-generated.md`

## Safety Rules

- Real runtime writes performed must remain `0`.
- No `modules/Inventory/` runtime directory may be created.
- No real migration path may be written.
- No Warehouse, Core, Activities, Notes, vendor, node_modules, public build, package, or composer files may be changed.
- Preview PHP files should pass syntax lint where possible.

## Current Limits

The generated files are structurally meaningful previews, not production-ready module output.
They are intended to verify file layout, naming, StandardDetailPage metadata shape, JsonResource contract, and frontend detail page consumption before the builder gains write-capable generation.
