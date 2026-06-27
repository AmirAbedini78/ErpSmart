# Resource Notes Integration

## Purpose
This document records the final, tested Notes integration contract for generated ERP resources. It is RAG-critical because the initial Warehouse Notes integration passed several superficial checks but failed during real POST persistence.

## Final Warehouse outcome
Warehouse Notes are confirmed working after these contracts were satisfied:

- `Warehouse::notes()` exists as a concrete relation on the Warehouse model.
- `Note::warehouses()` exists as a concrete relation on the Notes model.
- Warehouse Resource uses `AssociatesResources` so Notes POST payloads can sync `warehouses: [id]`.
- Warehouse model uses `HasTimeline` so timeline-mode requests are accepted.
- Warehouse frontend normalizes `resource.path` to `/warehouses/{id}` for Core record-tab components.
- `noteables` pivot has no timestamps in this installation, so Warehouse/Note relations must not call `withTimestamps()`.

## Required backend contract

```text
DomainModel::notes() concrete morph relation
Note::{resourcePlural}() concrete inverse morph relation
Resource uses AssociatesResources
Resource validation accepts via_resource={resourcePlural}
Timeline-enabled model when using timeline=1 tabs
Pivot relation matches actual pivot schema
```

## Required frontend contract

```text
Record detail page passes resourceName
Record detail page passes resourceId
Resource object contains path
Resource object contains {relation}_count defaults where needed
Record tab panel receives a stable resource object, not undefined
```

## Negative lessons learned

1. `resolveRelationUsing()` is not safe for inverse relations that are synced by Core `AssociatesResources` and `laravel-pivot-events`.
2. Dynamic relation closures can be reported to pivot touch logic as `Modules\Warehouse\Providers\{closure}` and cause a `BadMethodCallException` on the related model.
3. If a morph pivot table lacks `created_at` and `updated_at`, using `withTimestamps()` will cause `SQLSTATE[42S22]: Unknown column 'created_at'` during attach/sync.
4. Passing a resource object without `path` makes Core record-tab components call `/api/undefined/{relation}`.
5. A tinker read check is not enough; the real POST/sync flow must be tested because pivot events run only during write operations.

## Builder rule
For every future resource association feature, the Builder must generate both sides of the relation as real methods and must inspect the actual pivot schema before deciding whether timestamps are enabled.
