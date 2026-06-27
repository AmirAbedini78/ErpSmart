# Resource Field Type Mapping

## Purpose
This note records a Builder-critical rule learned from the Warehouse module: database column types, Eloquent casts, Resource field classes, frontend form controls, and validation rules must be generated together.

A generated module is incomplete when it creates a boolean database column but exposes that column as a text field in the Resource. The UI can then send arbitrary strings and the database can fail with SQL type errors.

## Correct generation chain

```text
migration column type -> model cast/default -> Resource Field class -> Resource validation -> frontend Resource UI -> API payload
```

## Boolean fields
For boolean columns such as `is_active`, generate this combination:

```text
Migration:     $table->boolean('is_active')->default(true)
Model:         protected $casts = ['is_active' => 'boolean']
Model default: protected $attributes = ['is_active' => true]
Resource:      Modules\Core\Fields\Boolean
Validation:    nullable|boolean or required|boolean depending on business rule
```

Do not use `Text::make()` for boolean fields. This creates a text input and can send invalid values such as `rt`, causing MySQL errors like:

```text
Incorrect integer value for column 'is_active'
```

## Current Warehouse mapping

```text
name        -> Text      -> string
code        -> Text      -> nullable string unique candidate
description -> Text      -> nullable string/text
is_active   -> Boolean   -> boolean default true
created_at  -> CreatedAt -> timestamp
updated_at  -> UpdatedAt -> timestamp
```

## Builder requirement
When Module Builder generates fields, it must maintain a single source of truth for field type mapping. Recommended metadata keys:

```json
{
  "name": "is_active",
  "db_type": "boolean",
  "default": true,
  "model_cast": "boolean",
  "resource_field": "Boolean",
  "validation": ["nullable", "boolean"],
  "ui_control": "toggle"
}
```

## Validation rule
Before accepting a generated module, the Builder must run a static consistency check:

```text
boolean db column must not map to Text field
integer db column must not map to free Text field unless intentionally configured
foreign key must map to BelongsTo/Select style field
long text should map to Textarea/Editor when available
```
