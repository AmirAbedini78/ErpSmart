# Warehouse Domain — First Builder-Aware Resource Module

## Status
MVP is now beyond smoke-test. `/warehouses` is registered in the Vue router and the module has been upgraded to use the Core Resource UI pattern for index/create/edit/detail.

## Purpose
Warehouse is the first manual test module for validating the ErpSmart entity lifecycle. It is not the final inventory system yet. Its job is to prove that a new business entity can be introduced through the Concord/ErpSmart module/resource architecture and later become a template for the internal Module Builder.

This module must therefore be treated as both:

```text
1. A real business module: warehouse master data.
2. A reference template for future Builder-generated modules.
```

## Current MVP scope
Implemented or expected in this stage:

```text
Module registration
Database table: warehouses
Model: Modules\Warehouse\Models\Warehouse
Policy: Modules\Warehouse\Policies\WarehousePolicy
Resource: Modules\Warehouse\Resources\Warehouse
Table: Modules\Warehouse\Resources\WarehouseTable
Backend Resource route compatibility via WithResourceRoutes
Frontend route registration via modules/Warehouse/resources/js/app.js
Root frontend import via resources/js/app.js
Resource index table via ResourceTable
Create slideover via create-fields endpoint
Edit slideover via update-fields endpoint
Detail page via DetailFields
Resource export capability via Exportable contract
Import capability via Importable contract
Custom Fields capability via AcceptsCustomFields and AcceptsUniqueCustomFields contracts
Import tracking column: import_id nullable indexed
Translations
Build validation
```

Still excluded from this MVP:

```text
stock movements
inventory balances
inventory valuation
products/items
warehouse locations/bins
purchase/sales integration
workflow triggers/actions
documents/notes/activity timeline integration
advanced dashboard metrics
```

## Business fields
Current table fields:

```text
id
name
code nullable unique candidate
description nullable
is_active boolean default true
created_at
updated_at
import_id nullable indexed // import/revert tracking; hidden from forms
```

Future warehouse master fields:

```text
manager_id
is_default
address
phone
sort_order
warehouse_type
branch_id/company_id if multi-company is introduced
```

## Runtime table naming
The migration table name is written as:

```text
warehouses
```

At runtime the application may use the configured database prefix, so SQL/debug output can show:

```text
tbl_warehouses
```

Do not manually use `tbl_warehouses` in model `$table`; keep the model table as `warehouses` so Laravel can apply the configured prefix.

## File map

```text
modules/Warehouse/module.json
modules/Warehouse/composer.json
modules/Warehouse/app/Providers/WarehouseServiceProvider.php
modules/Warehouse/app/Providers/RouteServiceProvider.php
modules/Warehouse/routes/api.php
modules/Warehouse/routes/web.php
modules/Warehouse/app/Models/Warehouse.php
modules/Warehouse/app/Policies/WarehousePolicy.php
modules/Warehouse/app/Resources/Warehouse.php
modules/Warehouse/app/Resources/WarehouseTable.php
modules/Warehouse/database/migrations/2026_06_12_184218_create_warehouses_table.php
modules/Warehouse/database/migrations/2026_06_19_160500_add_import_id_to_warehouses_table.php
modules/Warehouse/lang/en/warehouse.php
modules/Warehouse/resources/js/app.js
modules/Warehouse/resources/js/routes.js
modules/Warehouse/resources/js/views/WarehousesIndex.vue
modules/Warehouse/resources/js/views/WarehousesCreate.vue
modules/Warehouse/resources/js/views/WarehousesEdit.vue
modules/Warehouse/resources/js/views/WarehousesView.vue
resources/js/app.js
```

## Backend lifecycle

1. `modules_statuses.json` enables the module.
2. `module.json` registers `Modules\\Warehouse\\Providers\\WarehouseServiceProvider`.
3. `WarehouseServiceProvider` registers `Warehouse` in `protected array $resources`.
4. `register()` calls `$this->registerResources()` and registers `RouteServiceProvider`.
5. `Warehouse` implements `Tableable`, `WithResourceRoutes`, `Exportable`, `Importable`, `AcceptsCustomFields`, and `AcceptsUniqueCustomFields`.
6. Core Resource API exposes table, fields, create, update, retrieve, delete, actions, import/export, and custom field registration where available.
7. `import_id` exists on `warehouses` so Core import/revert can track imported rows safely.

Critical check:

```php
\Modules\Core\Facades\Innoclapps::resourceByName('warehouses')::class;
```

Expected:

```text
Modules\Warehouse\Resources\Warehouse
```

`resourceByName('warehouse')` is expected to be `null`; the resource name is plural.

## Frontend lifecycle

1. Root app imports `@/Warehouse/app.js` from `resources/js/app.js`.
2. `modules/Warehouse/resources/js/app.js` registers routes during `Innoclapps.booting`.
3. `routes.js` adds:

```text
/warehouses              -> warehouse-index
/warehouses/create       -> create-warehouse
/warehouses/:id          -> view-warehouse
/warehouses/:id/edit     -> edit-warehouse
```

4. `WarehousesIndex.vue` uses `ResourceTable` and `ResourceExport`.
5. `WarehousesCreate.vue` uses `getCreateFields()` and `createResource()`.
6. `WarehousesEdit.vue` uses `getUpdateFields()`, `retrieveResource()`, and `updateResource()`.
7. `WarehousesView.vue` uses `useResource()`, `getDetailFields()`, and `DetailFields`.

## 404 root cause fixed
The route `/warehouses` previously returned a frontend 404 because the module JS entry existed but was not imported into the root frontend bundle.

Fix:

```js
import '@/Warehouse/app.js'
```

must exist in:

```text
resources/js/app.js
```

This is a critical Builder rule: creating a module is not complete unless its frontend registration path is generated and included in the application bundle.


## 2026-06-19 Boolean field correction

`is_active` is a boolean database column and must be exposed as `Modules\Core\Fields\Boolean`, not `Text`.

Correct field mapping:

```text
is_active database type: boolean default true
model default: true
model cast: boolean
resource field: Boolean::make('is_active', ...)
validation: nullable|boolean
frontend control: boolean/toggle generated by Resource UI
```

Failure mode fixed:

```text
Text::make('is_active') allowed arbitrary values like `rt`, producing MySQL error 1366 incorrect integer value.
```

Builder implication: field generation must be schema-aware. The Builder must not generate UI field types independently from DB column metadata.


## 2026-06-19 Resource capability contracts enabled

Warehouse is now moving from basic Resource CRUD to first-class Core Resource behavior. The Resource implements these capability contracts:

```text
AcceptsCustomFields
AcceptsUniqueCustomFields
Exportable
Importable
Tableable
WithResourceRoutes
```

Why this matters:

```text
AcceptsCustomFields        -> Warehouse appears in the Custom Field resource selector.
AcceptsUniqueCustomFields  -> generated custom fields can opt into unique validation when field type supports it.
Exportable                 -> /api/warehouses/export and export-fields are valid.
Importable                 -> /api/warehouses/import/* endpoints are valid.
Tableable                  -> /api/warehouses/table uses WarehouseTable.
WithResourceRoutes         -> Core Resource CRUD endpoints are generated/allowed for warehouses.
```

Import requires schema support. The Core ImportController uses `import_id` to track and revert imported rows, so Warehouse must have:

```text
warehouses.import_id nullable indexed
model fillable: import_id
model cast: import_id => integer
```

Builder implication: resource capability contracts are not just UI switches. They may require database columns, model casts, policies, docs, and smoke tests. The Module Builder must generate capability-specific prerequisites atomically.


## 2026-06-19 Permission policy tightened

Warehouse is no longer using the permissive MVP policy where all authorization methods returned `true`. The policy now checks Core/Spatie permission names while still allowing super admins through the project-wide super-admin bypass.

Current policy mode:

```text
master_data_global
```

Supported Warehouse permission names:

```text
view all warehouses
create warehouses
edit all warehouses
delete any warehouse
bulk delete warehouses
export warehouses
import warehouses
```

Important rule: Warehouse currently has no owner/team column, so `own` and `team` permission variants are not considered sufficient. If a future version adds `owner_id`, `user_id`, `team_id`, or visibility groups, the Builder must generate schema, query scopes, and policy branches together.

Builder implication: authorization is a generated architecture layer, not a manual afterthought. The Builder must ask/select a policy mode for each generated Resource, for example `master_data_global`, `owned_by_user`, `team_visible`, or `visibility_group_based`.

## Builder implications
The future Module Builder must generate all of these layers atomically:

```text
module.json
ServiceProvider with $resources
RouteServiceProvider
migration
model
policy
resource
table
translations
frontend app.js
frontend routes.js
index/create/edit/detail views
root frontend registration or dynamic module manifest registration
SmartDocs domain file
RAG manifest JSON
history entry
```

A module that only has migration/model/resource but is missing frontend bundle registration will look valid in backend checks but still fail in Vue navigation.

## Validation checklist

```bash
# backend resource check
docker compose exec app php artisan tinker
```

```php
\Modules\Core\Facades\Innoclapps::resourceByName('warehouses')::class;
```

```bash
# frontend registration check
docker compose exec app sh -c "grep -n \"Warehouse/app\" resources/js/app.js"

# build
docker compose exec app php artisan optimize:clear
sudo rm -f public/hot
docker compose exec node npm run build
docker compose restart app nginx
```

Browser checks:

```text
http://localhost:8080/warehouses
http://localhost:8080/warehouses/create
http://localhost:8080/warehouses/{id}
http://localhost:8080/warehouses/{id}/edit
```

## Next development phases

```text
1. Verify ResourceTable, create, edit, detail view in browser.
2. Verify API endpoints for warehouses, table, create-fields, update-fields, detail-fields.
3. Run database migration for import_id.
4. Confirm export-fields/export endpoint.
5. Confirm import sample/upload endpoint.
6. Confirm Warehouse appears in Custom Fields resource selector.
7. Replace temporary permissive policy with real permission behavior if needed.
8. Add notes/documents/activities/audit only after master data resource is stable.
9. Add Warehouse Locations as the next child entity.
10. Add Products/Items.
11. Add Stock Movements and Balances.
```

## Validation addendum — boolean field

When testing create/update, inspect the browser payload. It must not contain free text for `is_active`. Acceptable payload values are boolean-style values such as `true`, `false`, `1`, or `0`, depending on how the Core Boolean field serializes values.

```text
Create Warehouse validation must send `is_active` as boolean, not arbitrary text.
```

## 2026-06-20 Permission UI and data-exposure fix

The first strict Warehouse policy revealed two integration issues:

1. `create warehouses` was checked by the policy but was not registered in the role permission UI.
2. A non-super-admin user could still open the Warehouse table and see rows because the table query was not filtered when the user lacked `view all warehouses`.

Fix applied:

```text
modules/Warehouse/app/Resources/Warehouse.php
modules/Warehouse/app/Policies/WarehousePolicy.php
modules/Warehouse/app/Models/Warehouse.php
modules/Warehouse/lang/en/warehouse.php
```

Warehouse now registers its own master-data permission group instead of using only `registerCommonPermissions()`. This avoids misleading `own/team` permissions for a model that has no owner/team columns yet.

Current permission matrix:

```text
view all warehouses       -> can list/view Warehouse records
create warehouses         -> can create Warehouse records; also controls Core import visibility for Importable resources
edit all warehouses       -> can update Warehouse records
delete any warehouse      -> can delete one Warehouse record
bulk delete warehouses    -> can run bulk delete when also allowed to delete each selected record
export warehouses         -> can export Warehouse records
import warehouses         -> reserved explicit capability for future Warehouse-specific import authorization
```

Important Builder rule: when a generated module is `master_data_global`, do not blindly generate `own/team` role UI entries unless the Builder also generates ownership/team columns, criteria classes, filters, and policy branches.

A table-level guard was also added. If the logged-in user lacks `view all warehouses`, the Warehouse table query is forced to return zero rows. This prevents rows from being visible through ResourceTable even when a frontend route/menu is reachable.



## 2026-06-20 Custom Fields and language stabilization step

Warehouse is ready for Core Custom Fields:

```text
Resource contract: AcceptsCustomFields
Unique custom fields: AcceptsUniqueCustomFields
Settings route: /settings/fields/warehouses
Frontend shortcut: Warehouses index dropdown -> Customize fields
```

The Warehouse index action menu now includes a super-admin-only shortcut to the Core field customization screen. This does not create a separate custom field system; it uses the existing Core `CustomFieldController`, Settings Fields UI, field manager, field visibility settings, and Resource field hydration pipeline.

Expected manual test:

```text
1. Login as super admin.
2. Open /warehouses.
3. Use the actions dropdown -> Customize fields.
4. Add a custom text field such as Storage Zone.
5. Save create/update/detail field settings.
6. Open /warehouses/create and confirm the custom field appears.
7. Create a Warehouse with the custom field value.
8. Open detail/edit and confirm value persists.
```

Language correction rule:

```text
Never bind a translation key that resolves to an array/object into a label/text prop.
Use leaf keys only, e.g. warehouse::warehouse.actions.customize_fields, not warehouse::warehouse.actions.
```

Warehouse permission labels were also made more explicit for `bulk_delete`, `export`, and `import` role UI views so the role screen has stable display strings.

## 2026-06-20 Permission cleanup, table columns, and row actions

User validation after the permission UI step found three practical issues:

1. Some stale/generic `Export` permission rows were still visible in the role UI.
2. A normal user could not easily see the full useful Warehouse table columns.
3. Row actions such as delete and clone were not visible in the data table.

Fix applied:

```text
modules/Warehouse/app/Resources/Warehouse.php
modules/Warehouse/database/migrations/2026_06_20_180000_cleanup_warehouse_permissions.php
docs/ai/05-rag/module-manifest/warehouse.json
```

Warehouse now has explicit row/bulk actions:

```text
CloneAction -> visible when the user can create warehouses
DeleteAction -> visible when the user can delete the current warehouse
```

Warehouse also implements `Cloneable` and provides a `clone(Model $model, int $userId)` method. Cloning generates safe copied names/codes to avoid the unique `code` constraint.

A cleanup migration removes stale Warehouse-related permission records that are not part of the canonical matrix:

```text
view all warehouses
create warehouses
edit all warehouses
delete any warehouse
bulk delete warehouses
export warehouses
import warehouses
```

Important Builder rule: when an AI Builder changes permission names during iteration, it must also generate a cleanup/sync migration. Removing stale permission code is not enough because Spatie permission records remain persisted in the database.

Column visibility adjustment: `description` is now visible on index/table by default. The hidden-by-design default columns remain `id`, `created_at`, and `updated_at`.


## 2026-06-20 Import/Export validation and boolean import normalization

Warehouse import/export is now treated as a Core Resource capability, not as a custom controller. The expected Core endpoints are:

```text
GET  /api/warehouses/export-fields
POST /api/warehouses/export
GET  /api/warehouses/import
GET  /api/warehouses/import/sample
POST /api/warehouses/import/upload
POST /api/warehouses/import/{id}
DELETE /api/warehouses/import/{id}
DELETE /api/warehouses/import/{id}/revert
```

The Warehouse model now normalizes `is_active` values in `setIsActiveAttribute()`. This protects create/update/import flows from raw CSV strings such as `yes`, `no`, `true`, `false`, `1`, `0`, `on`, and `off`.

Known validation item: the role UI can still show multiple generic `Export` rows. This should be investigated through the permission group registry before deleting records. The diagnostic command for runtime inspection is:

```php
collect(\Modules\Core\Facades\Permissions::groups()['warehouses']['views'] ?? [])->map(fn ($view) => [
    'view' => $view['view'],
    'as' => $view['as'],
    'keys' => $view['keys'],
    'permissions' => $view['permissions'],
]);
```

Do not treat this as a blocking issue for Import/Export functional testing unless it creates authorization failure.

## 2026-06-22 Notes integration

Warehouse now starts integrating with the shared Notes module.

### Backend

The Warehouse model exposes a `notes()` morph-to-many relationship:

```php
public function notes(): MorphToMany
{
    return $this->morphToMany(Note::class, 'noteable')->withTimestamps();
}
```

This uses the Notes module's shared `noteables` pivot. No warehouse-specific note table should be generated by the Builder for the standard notes capability.

### Frontend

`WarehousesView.vue` now uses a tabbed layout:

```text
Details tab
Notes tab
```

The Notes tab uses the existing Notes module components:

```text
@/Notes/components/RecordTabNote.vue
@/Notes/components/RecordTabNotePanel.vue
```

The Warehouse detail view now provides the synchronization callbacks expected by record-tab components:

```text
fetchResource
synchronizeResource
detachResourceAssociations
incrementResourceCount
decrementResourceCount
```

### Builder note

When the future Module Builder enables Notes for a generated module, it must generate both:

```text
model relationship
frontend detail tab wiring
```

Generating only the UI tab is not enough because the Core record-tab endpoint needs the model relationship. Generating only the relationship is not enough because users need the detail tab and synchronization hooks.

### Manual validation

Open `/warehouses/{id}`, switch to Notes, add a note, refresh, and verify the note persists.

## 2026-06-23 Notes tab 404 fix

After the first Notes integration, clicking the Notes tab redirected/ended on a 404. The issue was not the Vue tab itself. The Notes panel loads data through the generic associated-resource endpoint with `timeline=1`:

```text
/api/warehouses/{id}/notes?timeline=1
```

`AssociationsController::show()` rejects timeline requests with 404 unless the subject model uses the Core timeline subject trait:

```php
Modules\Core\Common\Timeline\HasTimeline
```

Warehouse already had the `notes()` morph-to-many relation, but it was missing `HasTimeline`. The model now uses:

```php
use Modules\Core\Common\Timeline\HasTimeline;
use Modules\Core\Resource\Resourceable;

class Warehouse extends Model
{
    use HasTimeline,
        Resourceable;
}
```

### Builder implication

For Notes-enabled modules, the Builder must generate both:

```text
model relation: notes()
model trait: HasTimeline
frontend tabs/panels
cache clear instructions for timeline-subjects/timelineables
```

Without `HasTimeline`, the UI can render the Notes tab, but the backend timeline association endpoint returns 404.

## Detail view stability rule - 2026-06-24

Warehouse detail now normalizes the resource object before rendering Core `DetailFields`.
The details tab is read-only and inline edit is disabled. Editing is performed through `/warehouses/{id}/edit`.
This avoids Vue render/update loops caused by `FieldInlineEdit` when a custom module response lacks the full inline-edit metadata expected by Core.


## Notes path contract fix - 2026-06-24

The Notes tab depends on Core `useRecordTab`, which builds its endpoint from `resource.path`. Warehouse detail responses may not include this key by default, so `WarehousesView.vue` now normalizes the detail resource with `path: /warehouses/{id}` plus default `notes` and `notes_count` values. Without this, the browser requests `/api/undefined/notes?page=1&per_page=15&timeline=1` and receives 404.


## Notes backend association contract fix - 2026-06-24

Warehouse Notes integration now includes the backend inverse association required by the Core Notes resource. `Warehouse::notes()` is only the forward relation. The Notes resource also needs `Note::warehouses()` so listing and creating notes through Core endpoints can resolve the Warehouse association.

The inverse relation is registered in `WarehouseServiceProvider` with `Note::resolveRelationUsing('warehouses', ...)`. This keeps the Notes module untouched while allowing Warehouse to participate in the shared `noteables` polymorphic pivot.


## Notes POST and offline build contract fix - 2026-06-24

Warehouse Notes now fixes the remaining create-note path by making the Warehouse resource association-aware and by allowing `via_resource=warehouses` in the Core Notes create request validation. The browser already targets `/api/notes?via_resource=warehouses&via_resource_id={id}`; the backend must explicitly accept this via-resource and then sync the `warehouses: [id]` association payload.

The Vite build is also made offline-capable by replacing `@concordcrm/vite-plugin-global-vue` with a local ERPSMART plugin that reads `node_modules/vue/dist/vue.global.js` instead of fetching `unpkg.com` during build.
