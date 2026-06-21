# Resource Custom Fields Contract

## Purpose

This document records how ErpSmart/Concord-style modules become compatible with the Core Custom Fields system. It is required for the future Module Builder and RAG/AI Agent because custom fields are a major low-code extension point.

## Required backend conditions

A Resource can participate in custom fields when it implements:

```php
Modules\Core\Contracts\Resources\AcceptsCustomFields
```

If unique custom fields are valid for the entity, also implement:

```php
Modules\Core\Contracts\Resources\AcceptsUniqueCustomFields
```

The Resource must be registered in the module provider and exposed in the application resource registry. The frontend Settings Fields page reads registered resources from script config.

## Required frontend conditions

The standard field customization route is:

```text
/settings/fields/{resourceName}
```

For Warehouse:

```text
/settings/fields/warehouses
```

A module may provide a shortcut to this route, but it must not implement a parallel custom field UI. The canonical UI is:

```text
modules/Core/resources/js/views/Settings/SettingsFields.vue
modules/Core/resources/js/components/CustomFields/*
```

## Language stability rule

Do not pass a translation key that resolves to an array/object into UI label props. Always reference leaf keys.

Good:

```text
warehouse::warehouse.actions.customize_fields
warehouse::warehouse.permissions.bulk_delete
```

Bad:

```text
warehouse::warehouse.actions
warehouse::warehouse.permissions
```

When a nested translation array is rendered directly, some Vue/i18n surfaces may display the JSON object instead of a human label.

## Builder generation rule

When the Builder enables custom fields for a generated module, it must generate/update:

```text
1. Resource contract: AcceptsCustomFields
2. Optional Resource contract: AcceptsUniqueCustomFields
3. Provider Resource registration
4. RAG manifest capability flag
5. SmartDocs history entry
6. Optional module UI shortcut to /settings/fields/{resourceName}
7. Language leaf keys for all new UI labels
```

## Warehouse current state

```text
Resource name: warehouses
Custom fields route: /settings/fields/warehouses
Shortcut location: modules/Warehouse/resources/js/views/WarehousesIndex.vue
Status: ready for manual custom-field validation
```
