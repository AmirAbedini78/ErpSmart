# 2026-06-24 - Warehouse Notes Pivot Fix

## Problem

Warehouse Notes GET/list was working after the route and association fixes, but creating a note failed with HTTP 500.

The request payload was valid:

- `via_resource=warehouses`
- `via_resource_id=13`
- `body=<p>...</p>`
- `warehouses=[13]`

Tinker confirmed:

- Warehouse resource exists.
- Warehouse resource is associateable.
- `Warehouse::notes()` resolves to `Modules\Notes\Models\Note`.
- `Note::warehouses()` resolves to `Modules\Warehouse\Models\Warehouse`.
- The `noteables` table only has `note_id`, `noteable_type`, `noteable_id`, and `tenant_id`.

## Fix

Removed `withTimestamps()` from both sides of the Warehouse/Note morph-to-many relation because the `noteables` pivot does not contain `created_at` or `updated_at` columns.

## Rule for Builder

When generating note-enabled modules, inspect the pivot schema before adding `withTimestamps()`. If the pivot table has no timestamp columns, generated relations must not call `withTimestamps()`.
