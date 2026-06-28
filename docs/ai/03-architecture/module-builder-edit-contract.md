# Module Builder Edit Contract

Status: canonical after Warehouse validation.
Updated: 2026-06-28

## Canonical pattern for CRM-style resources

Warehouse now follows the same edit contract used by first-party CRM resources such as Contacts, Companies, and Deals:

1. The detail page is a standalone record view.
2. The Edit action opens the Core floating resource modal.
3. PHP Resource actions expose `Action::make()->floatResourceInEditMode()`.
4. The Vue detail page calls `useFloatingResourceModal().floatResourceInEditMode(...)`.
5. The floating modal component must match the Core contract:
   - `visible`
   - `floatingReady`
   - `resource`
   - `fields`
   - `mode`
   - `updateHandler`
6. After a successful floating edit, the detail page must listen to `floating-resource-updated` and synchronize/fetch the current record without a browser refresh.

## Canonical Warehouse behavior

- Detail route: `/warehouses/:id`
- Edit behavior: Core floating modal, not a separate edit route from detail.
- Update synchronization: `WarehousesView.vue` listens for `floating-resource-updated`, checks the resource name/id, syncs the returned resource, and fetches the latest detail record.

## Superseded attempts

The following approaches were attempted during debugging and must not be used by the module builder:

- Mounting `WarehousesEdit.vue` directly inside the detail page.
- Teleporting the edit view manually to `body`.
- Navigating with `window.location` or a hard edit route from detail.
- Treating `WarehouseFloatingModal` as if Core passes `resourceId` directly. Core passes `resource`, `fields`, `mode`, and `updateHandler` instead.

These are kept only as debugging history in `docs/ai/04-docops/history`. The canonical contract above is the source of truth for future modules.


<!-- WAREHOUSE_EDIT_SUPERSEDED_ATTEMPTS_CANONICAL -->

## Superseded Warehouse edit attempts — DO NOT USE

These approaches were tried during debugging and are intentionally superseded by the canonical Core Floating Resource Modal contract above. They must not be used by the module builder, RAG retrieval, or future Warehouse-like modules.

- `detail_inline_edit`: mounting `WarehousesEdit.vue` directly inside `WarehousesView.vue`.
- `teleport_inline_edit`: Teleporting `WarehousesEdit.vue` to `body` from the detail page.
- `hard_edit_route`: navigating detail Edit to `/warehouses/{id}/edit` or using `window.location`.
- `resource_id_floating_modal_contract`: custom floating modal expecting `resourceId` and fetching its own data.

Canonical approach:

- Add `Action::make()->floatResourceInEditMode()` in the PHP Resource actions.
- Open edit via `useFloatingResourceModal().floatResourceInEditMode({ resourceName, resourceId })`.
- Register `{SingularName}FloatingModal` globally, for Warehouse: `WarehouseFloatingModal`.
- The floating modal must accept the Core props: `visible`, `floatingReady`, `resource`, `fields`, `mode`, and `updateHandler`.
- Detail pages must listen to `floating-resource-updated` and refresh/synchronize the current record without a browser refresh.
