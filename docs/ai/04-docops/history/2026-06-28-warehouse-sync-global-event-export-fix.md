# Warehouse Sync Global Event Export Fix

Status: applied

The Warehouse detail view listens to the first-party `floating-resource-updated` event so a successful Floating Resource Modal edit refreshes the current detail record without a browser reload.

A previous patch assumed `onGlobal` was exported by `modules/Core/resources/js/composables/useGlobalEventListener.js`; the local project does not export `onGlobal`. This fix detects the actual exported listener composable and rewrites `WarehousesView.vue` to use the project-local export.

Canonical rule for future modules:

- Do not invent event helper names.
- Reuse the actual exported helper from `useGlobalEventListener.js`.
- For CRM-style resources, use `Action::make()->floatResourceInEditMode()` and listen for `floating-resource-updated` on detail pages that must sync immediately after edit.
