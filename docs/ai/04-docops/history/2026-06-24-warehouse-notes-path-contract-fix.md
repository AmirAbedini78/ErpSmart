# 2026-06-24 - Warehouse Notes path contract fix

## Context

After adding `HasTimeline`, the backend timeline checks passed, but opening the Notes tab still returned 404.
The browser Network request was:

```text
/api/undefined/notes?page=1&per_page=15&timeline=1
```

This showed that the frontend Notes tab did not receive a valid `resource.path`.

## Decision

Normalize the Warehouse detail resource before passing it to Core record-tab components.

## Changes

- Add computed `resourcePath` in `WarehousesView.vue`.
- Normalize the detail resource with `path: /warehouses/{id}`.
- Normalize `notes` to an empty array and `notes_count` to zero when missing.
- Keep `HasTimeline` and the notes relationship in the backend model.

## Validation

1. Build frontend.
2. Open a Warehouse detail record.
3. Open Network tab.
4. Click Notes.
5. Confirm the request is `/api/warehouses/{id}/notes`, not `/api/undefined/notes`.
6. Create a note and refresh.

## Builder rule

Generated detail pages that use shared record tabs must provide the Core record-tab path contract. A model relation and `HasTimeline` are not enough if `resource.path` is missing on the frontend object.
