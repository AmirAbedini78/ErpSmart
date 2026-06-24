# 2026-06-22 — Warehouse Notes Integration

## Summary

Warehouse was connected to the shared Notes module as the next ecosystem capability after CRUD, permissions, custom fields, import/export, clone/delete, and offline Vue runtime stabilization.

## Files changed

```text
modules/Warehouse/app/Models/Warehouse.php
modules/Warehouse/resources/js/views/WarehousesView.vue
docs/ai/02-domains/warehouse.md
docs/ai/03-architecture/resource-notes-integration.md
docs/ai/04-docops/checklists/warehouse-notes-validation.md
docs/ai/04-docops/history/2026-06-22-warehouse-notes-integration.md
docs/ai/04-docops/task_state.json
docs/ai/05-rag/module-manifest/warehouse.json
```

## Backend change

The Warehouse model now exposes:

```php
public function notes(): MorphToMany
```

This relationship uses the Notes module's shared `noteables` morph pivot and allows the Core Resource timeline/record-tab request pattern to retrieve notes for a Warehouse record.

## Frontend change

`WarehousesView.vue` now contains a tabbed detail layout with:

```text
Details tab
Notes tab
```

The Notes tab uses:

```text
@/Notes/components/RecordTabNote.vue
@/Notes/components/RecordTabNotePanel.vue
```

The view now provides the resource synchronization callbacks expected by Notes components:

```text
fetchResource
synchronizeResource
detachResourceAssociations
incrementResourceCount
decrementResourceCount
```

## Builder implication

Notes integration is not a single frontend change. The Builder must generate both the model relation and detail-view wiring. Otherwise the UI can render but note create/load flows may fail.

## Validation status

Manual validation required in the running Docker environment:

```text
/warehouses/{id}
```

Expected behavior:

```text
Notes tab is visible
note can be added
note remains after refresh
no Vue injection/component errors appear
```
