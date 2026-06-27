# 2026-06-25 — Warehouse Detail Edit Route Fix

## Context

After Notes and Attachments were integrated into the Warehouse detail page, the top Edit button in the detail screen routed back to the index view instead of opening the edit form.

## Cause

The Warehouse detail page is custom. Its top edit action was using an unstable or incorrect route target. Because the generated/registered route name was not guaranteed to match the guessed name, the app resolved navigation incorrectly.

## Fix

Standardized the edit action to use a canonical path:

```text
/warehouses/{id}/edit
```

Added a `goToEdit()` helper in `WarehousesView.vue` and rewired the visible Edit button to call it.

## RAG Notes

For local AI / Builder generation:

- Do not guess route names for custom detail pages.
- Prefer canonical paths for resource actions unless route names are discovered.
- Detail-page features must be treated as modular capabilities, but base navigation must remain stable.
- The builder must be able to enable capabilities such as notes, media attachments, deletion, timeline, activities, documents, emails, calls, and custom fields without breaking base view/edit navigation.
