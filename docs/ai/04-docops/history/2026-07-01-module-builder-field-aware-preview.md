# Module Builder Field-Aware Preview

Date: 2026-07-01

## Summary

The Module Builder preview renderer now uses `definition.fields` instead of hard-coded Warehouse-like fields for generated preview files.

Updated generated preview families:

- Model fillable fields
- Model casts
- Migration columns
- Resource field definitions
- JsonResource output
- Entity-derived StandardDetailPage detail panel id

## Sample Definition

The Warehouse-like sample definition now includes `stock_quantity` as an integer field and `unit_cost` as a decimal field so field-aware generation can be verified beyond the original text/textarea/boolean fields.

## Safety

- Dry-run mode remains unchanged.
- Preview mode still writes only under `storage/app/module-builder-preview/{Module}/`.
- `--write` remains refused.
- No real `modules/Inventory` directory is created.
- No Warehouse, Core, migration, package/composer, vendor, node_modules, or public build files are changed.

## Verifier

Added:

```text
patches/verify_module_builder_field_aware_preview.php
```

The verifier checks that generated Model, Migration, Resource, and JsonResource files reflect fields from the JSON definition and that preview output stays inside the preview directory.
