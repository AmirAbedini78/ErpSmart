<!-- SUPERSEDED_BY_WAREHOUSE_FLOATING_MODAL_CONTRACT -->

> Superseded note: this history entry is retained for debugging/audit only. Do not use it as module-builder guidance. The canonical Warehouse edit contract is Core Floating Resource Modal + Core props + `floating-resource-updated` detail synchronization.
# 2026-06-28 — Warehouse Edit First-Party Floating Modal Fix

## Problem

Previous detail edit attempts used direct inline component mounting or hard route navigation. That diverged from first-party CRM resources and created UI instability.

## Discovery

- Contacts, Companies and Deals use `Action::make()->floatResourceInEditMode()` in PHP resource actions.
- Contacts/Companies/Deals do not use separate `/:id/edit` detail routes for normal CRM edit behavior.
- Activities are the exception and use named child router views for edit.

## Fix

- Added `WarehouseFloatingModal.vue`.
- Registered it globally in `modules/Warehouse/resources/js/app.js`.
- Updated `WarehousesView.vue` to use `useFloatingResourceModal().floatResourceInEditMode(...)`.
- Added `Action::make()->floatResourceInEditMode()` to `Warehouse` resource actions.
- Removed legacy direct inline edit modal mounting from `WarehousesView.vue`.

## Builder Learning

For generated CRM-style resources, prefer the first-party floating modal contract instead of custom hard routes.