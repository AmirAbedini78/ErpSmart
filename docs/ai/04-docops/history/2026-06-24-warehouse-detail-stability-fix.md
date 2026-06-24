# 2026-06-24 - Warehouse detail stability fix

## Context

After Warehouse Notes integration and `HasTimeline`, backend checks passed:

- `HasTimeline` trait was present.
- `Timelineables::hasTimeline($warehouse)` returned true.

However, opening a Warehouse detail record caused the frontend to enter a heavy update loop and made the SPA feel frozen.
The browser console showed repeated warnings from `FieldInlineEdit`, `DetailFields`, and `WarehousesView.vue`.

## Decision

The issue is not Notes backend routing. The issue is the custom Warehouse detail page rendering generic Core `DetailFields` while the resource object did not yet provide the full inline-edit contract expected by Core.

## Changes

- Normalize the resource passed to Details and Notes components.
- Add default `authorizations`, `_edit_disabled`, and `_sync_timestamp` values.
- Disable inline editing in the Warehouse detail tab.
- Keep edit flow through the dedicated edit page.
- Add missing Warehouse translation keys.

## Validation

1. Build frontend.
2. Open `/warehouses`.
3. Open a Warehouse detail record.
4. Details tab should render without update loop.
5. Notes tab should open without 404.
6. Add a note and refresh the page.

## Builder note

Custom Resource detail pages must either implement the full Core detail/inline-edit contract or explicitly disable inline editing for stable MVP modules.
