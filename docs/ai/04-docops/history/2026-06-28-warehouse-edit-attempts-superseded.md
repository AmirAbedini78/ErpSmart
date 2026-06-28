# Warehouse Edit Attempt Cleanup

Date: 2026-06-28
Status: documentation cleanup

## Summary

Several zip patches were generated while debugging the Warehouse edit behavior. Some represented failed or temporary approaches. They are not the canonical architecture.

## Canonical result

Use the first-party Core floating resource modal contract:

- `Action::make()->floatResourceInEditMode()` in the PHP Resource.
- `useFloatingResourceModal().floatResourceInEditMode(...)` in the detail view.
- A `WarehouseFloatingModal` component that accepts Core props: `visible`, `floatingReady`, `resource`, `fields`, `mode`, `updateHandler`.
- A `floating-resource-updated` listener in the detail view to refresh detail state without a full browser reload.

## Superseded / do not use

- Inline mounting `WarehousesEdit.vue` inside `WarehousesView.vue`.
- Manual `Teleport` for edit from the detail page.
- Hard route/window-location edit navigation from detail.
- Assuming Core passes `resourceId` into the floating component.

These docs remain as chronological history only. Future RAG/module-builder logic should rely on the canonical edit contract doc.