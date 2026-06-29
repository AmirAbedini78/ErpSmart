# Warehouse Detail Core JSON Resource Contract Final Fix

The Warehouse detail endpoint `/api/warehouses/{id}` must use the same JSON Resource contract as first-party Concord modules.

Root cause fixed:
- Earlier attempts created `Modules\Warehouse\Http\Resources\WarehouseResource`, but `modules/Warehouse/app/Resources/Warehouse.php` imported it as `WarehouseJsonResource` and still returned `WarehouseResource::class`.
- In the `Modules\Warehouse\Resources` namespace, that class reference does not reliably point to the HTTP JSON resource class.
- The JSON resource must extend `Modules\Core\Resource\JsonResource`, not Laravel's plain JSON resource.
- The JSON resource must call `withCommonData()` so Core can merge fields, authorizations, path, timestamps, and association counts.

Fix:
- Normalize the Warehouse Resource import to `use Modules\Warehouse\Http\Resources\WarehouseResource;`.
- Make `jsonResource()` return `WarehouseResource::class` using the imported class.
- Rewrite `WarehouseResource` to extend `Modules\Core\Resource\JsonResource` and call `withCommonData()`.

After applying, clear Laravel caches and restart app/nginx before testing `/warehouses/{id}`.