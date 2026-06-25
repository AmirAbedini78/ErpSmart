# Resource Notes Integration

A Resource can be Notes-enabled only when both frontend and backend association contracts are complete.

## Required backend contract

- The domain model must expose a concrete notes relation.
- The notes model must expose a concrete inverse relation for the domain resource.
- The resource must be associateable when the Core Notes resource posts association payloads.
- Do not use `resolveRelationUsing()` for inverse relations that are synced by Core `AssociatesResources`; pivot events and lazy touch require stable relation method names.

## Required frontend contract

- The record detail page must pass a stable resource name and resource id to `RecordTabNotePanel`.
- The resource object passed to the Notes panel must have a valid `path`.
- Read-only detail fields are safer until the resource response includes all inline-edit authorization metadata.

## Warehouse implementation note

Warehouse Notes require:

- `Warehouse::notes()` concrete morph relation.
- `Note::warehouses()` concrete inverse morph relation.
- `Warehouse` resource using `AssociatesResources`.
- Notes tab passing `resourceName = warehouses` and the record id.
