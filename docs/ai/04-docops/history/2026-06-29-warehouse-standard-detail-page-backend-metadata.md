# Warehouse StandardDetailPage Backend Metadata

Status: applied
Date: 2026-06-29

Phase 1 added backend-only `StandardDetailPage` metadata to the Warehouse Resource.

Changed behavior:
- Warehouse now registers backend detail page metadata for details, media, activities, and notes.
- The current hard-coded Warehouse frontend remains unchanged.
- Existing Notes and Activities `via_resource=warehouses` validation hooks remain unchanged.

Registered metadata:
- Panel `warehouse-detail-panel` uses `resource-details-panel`.
- Panel `media` uses `resource-media-panel`.
- Tab `activities` uses `activities-tab` / `activities-tab-panel` with order `15`.
- Tab `notes` uses `notes-tab` / `notes-tab-panel` with order `35`.

Next phase:
- Phase 2 should update `WarehousesView.vue` to consume `resourceInformation.value.detailPage` and render panels/tabs dynamically.
- Do not remove hard-coded frontend tabs until the dynamic frontend verifier and manual Warehouse detail tests pass.
