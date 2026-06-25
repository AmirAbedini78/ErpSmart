# Resource Media / Attachments Integration

## Warehouse Implementation Note
Warehouse uses Core media/attachments through:

- `Modules\Core\Contracts\Resources\Mediable` on the resource.
- `Modules\Core\Common\Media\HasMedia` on the model.
- Core media endpoint handled by `Modules\Core\Http\Controllers\Api\Resource\MediaController`.
- Frontend `ResourceMediaPanel` inside the custom `WarehousesView.vue`.

## Custom Detail View Contract
Because Warehouse does not use the stock generic record-detail renderer, panel components must receive the context that the generic renderer normally provides:

```js
const attachmentsPanel = computed(() => ({
  id: 'warehouse-media',
  component: 'resource-media-panel',
  name: 'media',
  heading: 'Attachments',
}))

provide('record', safeResource)
provide('resource', safeResource)
provide('resourceName', resourceName)
provide('resourceId', warehouseId)
```

The `ResourceMediaPanel` must also be guarded until the record id exists:

```vue
<ResourceMediaPanel
  v-if="safeResource.id"
  :panel="attachmentsPanel"
  :resource-name="resourceName"
  :resource-id="safeResource.id"
  :resource="safeResource"
/>
```

## Known Failure Mode
If the panel object is missing, the panel may throw:

```text
Cannot read properties of undefined (reading 'id')
```

## Notes Integration Checkpoint
The prior Notes integration established these RAG rules:

- `Warehouse::notes()` is required.
- `Note::warehouses()` must be a real model method, not a dynamic `resolveRelationUsing` relation, because pivot events/touches rely on relation names.
- The `noteables` pivot has no timestamps, so `withTimestamps()` must not be used.
- Frontend record-tab components require a valid `resource.path` such as `/warehouses/{id}`.
