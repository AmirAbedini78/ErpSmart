# Resource Import/Export Architecture

## Purpose

This document records how ERPSMART modules should integrate with the Core Resource import/export engine. Warehouse is the first generated/custom module used as the reference implementation.

## Required backend contracts

A module that supports CSV/Excel export should implement:

```php
Modules\Core\Contracts\Resources\Exportable
```

A module that supports CSV import should implement:

```php
Modules\Core\Contracts\Resources\Importable
```

For imported records to be tracked and reverted, the database table must include:

```text
import_id nullable indexed
```

The model should include `import_id` in `$fillable` and cast it as integer.

## Core API endpoints

The Core module provides the routes. A generated module should not create duplicate import/export controllers unless the business process is truly custom.

```text
GET  /api/{resource}/export-fields
POST /api/{resource}/export
GET  /api/{resource}/import
GET  /api/{resource}/import/sample
POST /api/{resource}/import/upload
POST /api/{resource}/import/{id}
DELETE /api/{resource}/import/{id}
DELETE /api/{resource}/import/{id}/revert
```

For Warehouse, `{resource}` is `warehouses`.

## Boolean field import rule

CSV imports often submit strings. If a generated module has boolean/tinyint fields, the Builder must generate normalization close to the model layer. Warehouse uses:

```php
public function setIsActiveAttribute(mixed $value): void
```

This prevents values like `yes`, `no`, `true`, `false`, `1`, `0`, `on`, and `off` from reaching MySQL as raw strings.

## Permission UI warning

Role UI data is generated from the runtime permission registry, while actual permission rows are persisted in the database. Exportable resources may also receive Core-generated export capability views. If duplicate generic `Export` rows appear, inspect:

```php
\Modules\Core\Facades\Permissions::groups()['warehouses']['views']
```

before deleting permissions. Builder-generated permission cleanup should operate on internal permission names, not translated labels.

## Builder checklist

When the Builder generates an import/export-capable Resource, it must generate or validate:

```text
Exportable contract
Importable contract
import_id migration
model fillable/cast for import_id
boolean/value normalization for non-string fields
policy methods: export/import
role UI permission registration
UI import/export entry points
SmartDocs history entry
RAG manifest capability flags
manual API smoke-test instructions
```
