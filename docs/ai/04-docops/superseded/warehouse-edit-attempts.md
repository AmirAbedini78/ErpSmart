# Warehouse edit attempts — superseded / do not use

Status: superseded
Canonical replacement: Core Floating Resource Modal contract
Last updated: 2026-06-28

## Do not use these patterns

1. Direct inline mount of `WarehousesEdit.vue` inside `WarehousesView.vue`.
2. Teleport-based inline edit modal from the detail page.
3. Hard route or `window.location` navigation from detail to `/warehouses/{id}/edit`.
4. Custom floating modal contracts that expect `resourceId` and fetch/update their own record.

## Use this pattern instead

Warehouse follows the same CRM-style edit contract as Contacts, Companies, and Deals:

- Resource action: `Action::make()->floatResourceInEditMode()`.
- View action: `floatResourceInEditMode({ resourceName, resourceId })`.
- Global component name: `WarehouseFloatingModal`.
- Core modal props: `visible`, `floatingReady`, `resource`, `fields`, `mode`, `updateHandler`.
- Detail update sync: listen to `floating-resource-updated` and update/fetch the active record.

This file exists so RAG and the module builder can classify older debug patches as failed/superseded attempts, not as architecture guidance.