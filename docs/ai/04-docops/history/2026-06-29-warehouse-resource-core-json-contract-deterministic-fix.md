# Warehouse Resource Core JSON Contract Deterministic Fix

WarehouseResource must extend `Modules\Core\Resource\JsonResource` rather than Laravel's plain JsonResource.

Reason:
- Concord/ErpSmart `ResourceRequest::toResponse()` calls Core helper methods such as `withActions()` on the resource response.
- A plain Laravel JsonResource does not provide those methods and throws `Call to undefined method ...::withActions()`.

This patch intentionally overwrites `modules/Warehouse/app/Http/Resources/WarehouseResource.php` deterministically instead of using fragile regex replacements.
