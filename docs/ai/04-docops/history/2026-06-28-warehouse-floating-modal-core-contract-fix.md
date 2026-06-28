<!-- SUPERSEDED_BY_WAREHOUSE_FLOATING_MODAL_CONTRACT -->

> Superseded note: this history entry is retained for debugging/audit only. Do not use it as module-builder guidance. The canonical Warehouse edit contract is Core Floating Resource Modal + Core props + `floating-resource-updated` detail synchronization.
# Warehouse Floating Modal Core Contract Fix

The Warehouse edit floating modal must follow the first-party Core contract used by `TheFloatingResourceModal.vue`.

Core does **not** pass `resourceId` to the resource floating modal component. It passes:

- `visible`
- `floating-ready`
- `resource`
- `fields`
- `mode`
- `update-handler`

The Warehouse floating modal was rewritten to consume that contract directly instead of retrieving/updating the record independently.

Builder note: CRM-style module edit should use `floating_edit_modal` with a module-specific `{SingularName}FloatingModal` component that accepts the Core floating modal props.