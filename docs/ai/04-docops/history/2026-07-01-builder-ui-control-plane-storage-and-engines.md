# Builder UI Control Plane, Storage, And Engine Probe

Date: 2026-07-01

## Summary

Created a docs-only architecture probe for moving the Module Builder direction toward a UI-first Builder Control Plane.

## Added

- `docs/ai/03-architecture/current-custom-fields-storage-probe.md`
- `docs/ai/03-architecture/module-builder-storage-strategy.md`
- `docs/ai/03-architecture/module-builder-performance-and-data-architecture.md`
- `docs/ai/03-architecture/module-builder-engine-boundaries.md`
- `docs/ai/03-architecture/module-builder-ui-control-plane.md`
- `docs/ai/03-architecture/builder-ui-entrypoint-options.md`
- `patches/verify_builder_ui_control_plane_probe.php`

## Notes

The probe documents Builder Studio plus embedded Super Admin/Settings customization as a hybrid UI model, with both surfaces using the same backend Builder Control Plane.

The existing Artisan command remains an engineering harness only. No runtime source files were changed.

The custom fields probe found that Core custom field metadata is stored in Core tables, while regular values are added as physical columns on resource tables and multi-option values use a pivot. This supports the recommendation that Builder definitions should be versioned JSON, but published runtime business data should be relational/published, not only JSON.

The optional lifecycle JSON was not created in this pass because `docs/ai/05-rag/contracts` is currently owned by root and the task does not require that optional artifact.
