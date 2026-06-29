# Warehouse Activity Associations JSON Resource Fix

Status: canonical follow-up fix for Warehouse Activities.

Problem:
- Activity creation worked for Warehouse.
- Opening/editing an Activity association popover called `/api/associations/activities/{id}` and failed with `Class name must be a valid object or a string` in `Resource::createJsonResource()`.

Root cause:
- Once Activity gained the `warehouses()` relation, Core association discovery correctly considered Warehouse an associateable resource for Activity.
- Warehouse Resource did not yet expose a JSON resource class, so Core could not serialize Warehouse records for the association picker.

Fix:
- Add `Modules\Warehouse\Http\Resources\WarehouseResource`.
- Add `Warehouse::jsonResource()` to return that class.
- Extend Activity pivot touch/detach relation lists to include `warehouses`.
- Keep `next_activity_id` cleanup limited to built-in models because the Warehouse table does not include next-activity columns.

Builder/RAG rule:
- Any custom resource that becomes associateable must provide a JSON resource class before it is exposed through Core association endpoints.