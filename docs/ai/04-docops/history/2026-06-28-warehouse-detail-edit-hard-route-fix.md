<!-- SUPERSEDED_BY_WAREHOUSE_FLOATING_MODAL_CONTRACT -->

> Superseded note: this history entry is retained for debugging/audit only. Do not use it as module-builder guidance. The canonical Warehouse edit contract is Core Floating Resource Modal + Core props + `floating-resource-updated` detail synchronization.
# Warehouse Detail Edit Hard Route Fix

The Warehouse detail edit action was stabilized by removing the inline-edit experiment and making the detail edit action navigate to the canonical full page URL `/warehouses/{id}/edit`.

Reason:
- Inline edit did not mount reliably from the detail page.
- The previous patch accidentally placed the edit click handler on the back button and left edit buttons without click handlers.
- Builder base navigation contract must be deterministic: index/create/view/edit routes should be explicit top-level routes, with edit ordered before view.

Builder rule:
- Every generated module must include explicit top-level routes for:
  - `/resource`
  - `/resource/create`
  - `/resource/{id}`
  - `/resource/{id}/edit`
- Edit route must be registered before view route.
- Detail edit buttons should use a canonical path and not depend on nested index modal routing unless a module explicitly opts into a tested inline-edit feature.
