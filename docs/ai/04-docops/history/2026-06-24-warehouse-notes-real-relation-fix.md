# 2026-06-24 — Warehouse Notes real relation fix

## Problem
Warehouse Notes POST passed validation and route checks, but failed with:

```text
Call to undefined method Modules\Notes\Models\Note::Modules\Warehouse\Providers\{closure}()
```

## Root cause
The Warehouse module registered the inverse Note relation using `Note::resolveRelationUsing('warehouses', Closure)`. This works for simple reads, but the Core pivot touch system and `laravel-pivot-events` expect a stable relation method name when `sync()` fires pivot events.

Dynamic relation closures may be reported as the relation name, causing `LazyTouchesViaPivot` to call an invalid method on the Note model.

## Fix
Use a concrete `warehouses()` method on `Modules\Notes\Models\Note` and remove the dynamic `resolveRelationUsing()` registration from `WarehouseServiceProvider`.

## Builder rule
For Resource associations that are persisted through Core `AssociatesResources`, do not rely on dynamic Eloquent relations for the inverse side. Generate concrete relation methods whenever pivot sync events, lazy touch, timeline, notes, documents, or activities are involved.
