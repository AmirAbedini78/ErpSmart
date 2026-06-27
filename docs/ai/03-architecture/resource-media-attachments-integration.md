# Resource Media / Attachments Integration

## Purpose

This document records the implementation pattern for attaching files to ERP resources in ErpSmart/ConcordCRM. It is written for local AI/RAG and for the future AI Module Builder.

## Final Warehouse status

Warehouse now supports generic resource attachments through the Core media system:

- `Warehouse Resource` implements `Modules\Core\Contracts\Resources\Mediable`.
- `Warehouse Model` uses `Modules\Core\Common\Media\HasMedia`.
- `Warehouse Model` implements `Modules\Core\Contracts\Resources\Resourceable` so Core pivot changelog listeners accept it.
- `WarehousesView.vue` renders Core `ResourceMediaPanel` in a custom Attachments tab.
- Detail response keeps `media` loaded so attachments remain visible after navigation.
- Attachment delete UI requires the same authorization shape that Core detail pages normally provide.

## Backend contract

A resource can use the Core resource media endpoint only when:

```php
use Modules\Core\Contracts\Resources\Mediable;

class Warehouse extends Resource implements Mediable, WithResourceRoutes
{
    // ...
}
```

The model must use Core media behavior:

```php
use Modules\Core\Common\Media\HasMedia;
use Modules\Core\Contracts\Resources\Resourceable as ResourceableContract;
use Modules\Core\Concerns\Resourceable;

class Warehouse extends Model implements ResourceableContract
{
    use HasMedia;
    use Resourceable;
}
```

The resource media endpoint is shared by Core:

```text
POST   /api/{resource}/{resourceId}/media
DELETE /api/{resource}/{resourceId}/media/{media}
```

## Important bug learned

When `attachMedia()` fires pivot events, Core changelog expects the model to be both:

```text
Modules\Core\Models\Model
Modules\Core\Contracts\Resources\Resourceable
```

Using only the `Resourceable` trait is not enough. The model must implement the contract.

## Frontend contract for custom detail pages

Core modules normally render media through a generic resource detail renderer and `Panel::make('media', 'resource-media-panel')`.

Warehouse has a custom detail page, so it must explicitly provide:

```js
<ResourceMediaPanel
  v-if="safeResource.id"
  :panel="attachmentsPanel"
  :resource-name="resourceName"
  :resource-id="safeResource.id"
  :resource="safeResource"
/>
```

And provide context:

```js
provide('record', safeResource)
provide('resource', safeResource)
provide('resourceName', resourceName)
provide('resourceId', warehouseId)
```

## Persistence contract

Upload success is not enough. The detail API response must carry media when the record is fetched again.

For Warehouse this was solved by eager-loading media on the model:

```php
protected $with = [
    'media',
];
```

For future builder-generated modules, prefer an explicit resource display query hook if available in that module's resource contract. If not, model-level eager loading is acceptable for the first stable module template.

## Delete action UI contract

The delete X in Core media UI can be hidden if a custom detail page normalizes authorizations too defensively. A previous stability fix set:

```js
update: false
```

That prevents Core panel actions from rendering. For media-capable detail pages, the record should expose update authorization if the backend did not send it:

```js
authorizations: {
  view: true,
  update: true,
  delete: false,
  ...(value.authorizations || {}),
}
```

Media items should also preserve or provide item-level authorization data:

```js
media: Array.isArray(value.media)
  ? value.media.map(media => ({
      ...media,
      authorizations: {
        delete: true,
        ...(media.authorizations || {}),
      },
    }))
  : []
```

Server-side authorization remains the source of truth. UI authorization flags only control visibility of buttons.

## Builder feature toggle

Feature key:

```json
{
  "feature": "resource.detail.attachments",
  "requires": [
    "resource.crud",
    "resource.detail_page",
    "resource.resourceable_model",
    "resource.with_resource_routes"
  ],
  "backend": [
    "Resource implements Mediable",
    "Model uses HasMedia",
    "Model implements Resourceable contract",
    "Detail query/serialization returns media"
  ],
  "frontend": [
    "Attachments tab",
    "ResourceMediaPanel",
    "panel metadata",
    "record/resource provides",
    "media normalization",
    "authorization normalization"
  ]
}
```
