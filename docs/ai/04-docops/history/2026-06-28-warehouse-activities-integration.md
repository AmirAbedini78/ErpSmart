# Warehouse Activities Integration

Date: 2026-06-28
Status: applied

Warehouse now follows the first-party Activities contract:

- `Warehouse` model uses `HasActivities`.
- `Activity` model knows the `warehouses()` morph relation.
- `Warehouse` resource exposes `CreateRelatedActivityAction::make()->onlyInline()`.
- `Warehouse` resource display query eager loads activities and incomplete count.
- `WarehousesView.vue` renders `ActivitiesTab` and `ActivitiesTabPanel`.
- Warehouse service provider validates `via_resource=warehouses` for related activity creation.

This is the canonical implementation for future module-builder/RAG guidance.