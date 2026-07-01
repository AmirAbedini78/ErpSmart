# Module Builder Capability-Aware Preview

Date: 2026-07-01

## Summary

Added capability-aware preview fixtures and verifier for the raw Module Builder.

The preview renderer now gates optional platform features from the JSON definition:

- media
- notes
- comments
- activities
- activity comments
- activity associations
- import/export/clone/table/global search/quick create
- floating modal

Unsupported schema-known capabilities warn instead of generating unsafe APIs.

## Added

- `docs/ai/05-rag/examples/definition-driven-capabilities-off-module.json`
- `docs/ai/05-rag/examples/definition-driven-capabilities-on-module.json`
- `docs/ai/03-architecture/module-builder-capability-rendering-contract.md`
- `patches/verify_module_builder_capability_aware_preview.php`

## Safety

- Preview writes stay under `storage/app/module-builder-preview`.
- Real runtime modules are not created.
- Warehouse/Core/runtime migrations/package/composer/vendor/build files are not changed.
