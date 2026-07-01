# Current Custom Fields Storage Probe

Status: architecture probe
Date: 2026-07-01

## Summary

The current ERPSMART custom field implementation is a Core platform capability, not a per-module ad hoc JSON blob. Custom field metadata is stored in Core tables, but most custom field values are stored as real columns added to each target resource table. Multi-option custom fields use a polymorphic pivot table.

This is important for the Builder Control Plane: Builder definitions can be versioned JSON, but published runtime business data should be relational/published, not only JSON.

## Source Evidence

- `modules/Core/database/migrations/2020_04_19_093541_create_custom_fields_table.php`
- `modules/Core/database/migrations/2020_04_20_093647_create_custom_field_options_table.php`
- `modules/Core/database/migrations/2020_04_20_144421_create_model_has_custom_field_options_table.php`
- `modules/Core/app/Models/CustomField.php`
- `modules/Core/app/Models/CustomFieldOption.php`
- `modules/Core/app/Fields/CustomFieldService.php`
- `modules/Core/app/Fields/CustomFieldFileCache.php`
- `modules/Core/app/Http/Requests/CustomFieldRequest.php`
- `modules/Core/app/Http/Controllers/Api/CustomFieldController.php`
- `modules/Core/app/Http/Resources/CustomFieldResource.php`
- `modules/Core/resources/js/views/Settings/SettingsFields.vue`
- `modules/Core/resources/js/components/CustomFields/CustomFieldsForm.vue`
- `modules/Core/app/Fields/FieldsManager.php`
- `modules/Core/app/Http/Controllers/Api/FieldSettingsController.php`

## Metadata Storage

`custom_fields` stores:

- `resource_name`
- `field_type`
- `field_id`
- `label`
- `is_unique`

The table has a unique index on `resource_name` and `field_id`.

`custom_field_options` stores option metadata for optionable fields:

- `custom_field_id`
- `name`
- `swatch_color`
- `display_order`

The table has a unique index on `custom_field_id` and `name`.

## Value Storage

`CustomFieldService::create()` creates a `CustomField` metadata row, then calls `createColumn()`. `createColumn()` looks up the field class through `Fields::customFieldable()` and calls that field class's `createValueColumn()` on the target resource model table.

This means regular custom field values are stored as physical columns on the resource table, not in one JSON value column.

For multi-option fields, `model_has_custom_field_options` stores:

- `model_id`
- `model_type`
- `custom_field_id`
- `option_id`

The pivot has an index on `model_id` and `model_type`, and foreign keys to `custom_fields` and `custom_field_options`.

## Resource Attachment

Custom fields are attached by `resource_name`. `CustomFieldRequest` only accepts resources registered in `Innoclapps::registeredResources()` that implement `AcceptsCustomFields`. Unique custom fields are additionally gated by `AcceptsUniqueCustomFields` and supported field type checks.

This is a reusable pattern for Builder-generated modules: generated resources should opt into custom fields through the same contracts when the definition enables them.

## Validation And UI

`CustomFieldRequest` validates:

- resource name
- label
- field type
- unique support
- generated `field_id`
- option rules for optionable fields

`CustomFieldsForm.vue` uses `scriptConfig('fields.custom_fields')`, the Core custom field prefix, and current resource metadata to decide available field types and unique support.

Field settings are separate from custom field definitions. `FieldsManager::customize()` saves per-group/per-view customization into settings keys shaped as `fields-{group}-{view}` and JSON-encodes the posted settings. `FieldSettingsController` exposes admin endpoints for reading, saving, and resetting these settings.

## Cache Behavior

`CustomFieldFileCache` uses `Cache::driver('file')` with key `custom_fields`. `CustomField` and `CustomFieldOption` refresh that cache on save/delete.

Builder publish and rollback must flush or refresh custom-field and resource metadata caches after changing published module resources or fields.

## DataView And Table/View JSON Storage

The table/view system uses `data_views` and `data_view_user_configs`.

`data_views` contains `rules` as `longText` and `config` as `text`; `DataView` casts `rules` to array and `config` to `AsArrayObject`. This is suitable for user view configuration and filters, not for high-volume operational facts.

## Stability For Large Data

The current custom field design is stronger than pure JSON for queryable business data because values become actual columns on business tables. Risks remain:

- adding and dropping columns from web/admin actions can be expensive on large tables
- deleting a custom field drops the column, so data loss is immediate unless backed up
- deleting options can null single-option values or remove pivot rows
- field settings JSON can reference fields that no longer exist, although `CustomFieldService` removes deleted field usage from `DataView` rules

## Builder Reuse Guidance

Reuse:

- `AcceptsCustomFields` and `AcceptsUniqueCustomFields`
- Core field classes and field settings UI patterns
- admin-only custom field endpoint pattern
- cache refresh discipline
- resource-name scoped configuration

Do not reuse directly as the Builder's source of truth:

- mutable settings JSON as the only module definition
- DataView JSON config as a module schema
- preview generated files as canonical definitions
- custom field column mutation as a substitute for Builder publish manifests

## Unknown / Needs Deeper Probe

- Exact storage driver used in production for `settings()` depends on deployment config.
- Tenant-specific custom field isolation needs deeper SaaS probing before Builder publish supports tenant-scoped definitions.
- Large-table DDL behavior should be tested against the target MySQL/PostgreSQL deployment before allowing UI-triggered publish.
