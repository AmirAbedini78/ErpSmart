# Warehouse Detail Sync After Floating Edit

Date: 2026-06-28
Status: applied

## Problem

After the Warehouse record was edited through the Core floating modal, the modal saved correctly, but the Warehouse detail page still showed stale data until a manual browser refresh.

## Cause

The Core floating modal emits the global event `floating-resource-updated` after a successful update. The Warehouse detail view was not listening to this event, so the local `useResource` detail state did not synchronize after the modal save.

## Fix

`modules/Warehouse/resources/js/views/WarehousesView.vue` now listens to `floating-resource-updated`, checks that the updated resource is the same Warehouse record, synchronizes the returned resource, then fetches and normalizes the latest detail record.

## Builder lesson

Any CRM-style detail page using Core floating edit must implement post-update detail synchronization. This should be part of the reusable module builder checklist.