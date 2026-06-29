# Warehouse Resource Core JsonResource Contract Fix

Fixes `/api/warehouses/{id}` after adding a custom `WarehouseResource`.

The first attempt created a JSON resource but extended Laravel's plain `Illuminate\Http\Resources\Json\JsonResource` contract. Concord/Core wraps resource responses and calls methods such as `withActions()`. Those methods live on `Modules\Core\Resource\JsonResource`, not on Laravel's plain resource class.

Canonical rule for future module builder/RAG:

- Custom resource classes used by `Resource::jsonResource()` must extend `Modules\Core\Resource\JsonResource`.
- Do not extend `Illuminate\Http\Resources\Json\JsonResource` directly for CRM resources.
- Verify detail endpoints after adding `jsonResource()` because `ResourceRequest::toResponse()` decorates the response with Core methods.
