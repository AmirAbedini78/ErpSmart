# Warehouse Activity Timeline & Comments Final Fix

This patch fixes the remaining Warehouse activity integration issues after the detail, activity create, and association flows were restored.

## Fixes

- `Modules\Warehouse\Resources\Warehouse` now implements `Modules\Comments\Contracts\PipesComments`.
  - This allows `/api/activities/{id}/comments?via_resource=warehouses&via_resource_id={id}` to pass CommentController validation.
- `RelatedActivityCreate.vue` sends both:
  - top-level `warehouses: [warehouseId]`
  - nested `associations: { warehouses: [warehouseId] }`
  so ResourceController can persist the `activityables` pivot and the UI popover still has its expected shape.
- `RelatedActivity.vue` increments `comments_count` after comment creation so comments become visible immediately even when the activity previously had zero comments.

## Notes

Existing activities created before this patch may be orphaned from Warehouse if no `activityables` pivot exists. Use the explicit repair helper with a known activity id:

```bash
docker compose exec app php patches/repair_warehouse_activity_link.php 13 <activity_id>
```