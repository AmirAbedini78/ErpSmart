<!-- SUPERSEDED_BY_WAREHOUSE_FLOATING_MODAL_CONTRACT -->

> Superseded note: this history entry is retained for debugging/audit only. Do not use it as module-builder guidance. The canonical Warehouse edit contract is Core Floating Resource Modal + Core props + `floating-resource-updated` detail synchronization.
# 2026-06-25 — Warehouse Detail Inline Edit Modal Fix

The route-based edit action from the custom Warehouse detail page still fell back
to the index screen in the current routing setup. The fix changes the detail Edit
action to open `WarehousesEdit.vue` inline as a modal/slideover.

Builder lesson:

- `index/create/view/edit` routes are base contracts.
- For custom detail pages, edit navigation must be tested as a separate feature.
- Reusable edit forms should accept explicit `recordId` and close behavior props.
