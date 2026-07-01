# Module Builder Preview Renderer

Date: 2026-07-01

## Summary

Phase 1 added preview rendering to the ERPSMART Module Builder command.
Dry-run behavior remains unchanged and still performs zero writes.

Preview mode writes generated files only under:

```text
storage/app/module-builder-preview/{Module}/
```

For the current Warehouse-like sample definition, preview output is rendered under:

```text
storage/app/module-builder-preview/Inventory/
```

## Safety

- Real runtime writes remain disabled.
- `--write` is refused.
- Running without `--dry-run` or `--preview` is refused.
- No Warehouse runtime source was changed.
- No Core source was changed.
- No real migrations, package/composer files, vendor files, node_modules files, or public build files were changed.

## Verifier

Added:

```text
patches/verify_module_builder_preview_renderer.php
```

The verifier checks dry-run output, preview output, preview file placement, absence of real `modules/Inventory` writes, generated preview PHP syntax, JsonResource contract, StandardDetailPage metadata, and frontend `resourceInformation.value.detailPage` consumption.

## Next Step

The next safe task is to inspect the generated preview files against Warehouse and first-party module patterns, then tighten renderer templates one file family at a time before enabling any real write mode.
