# Module Builder Field Rendering Contract

Status: Preview-only Phase 1
Date: 2026-07-01

## Purpose

The Module Builder preview renderer must use `definition.fields` as the source of truth for generated preview files.
Hard-coded Warehouse-like fields are no longer acceptable for preview output.

## Field Consumers

The current preview renderer consumes fields in these generated file families:

- Model fillable fields and casts
- Migration columns
- Resource field definitions
- JsonResource output
- StandardDetailPage detail panel id

## Type Mapping

Current preview mappings:

- `id`: migration `id()`, resource `ID`
- `text`: migration `string()`, resource `Text`
- `textarea`: migration `text()`, resource `Textarea`
- `boolean`: migration `boolean()`, model/json boolean casts, resource `Boolean`
- `integer`: migration `integer()`, model/json integer casts, resource `Number`
- `decimal`: migration `decimal(15, 2)`, model `decimal:2`, json float cast, resource `Number`
- `date`: migration `date()`, model `date`, resource `Date`
- `datetime`: migration `dateTime()`, model `datetime`, resource `DateTime`
- `select`: migration `string()`, resource `Select`
- `belongsTo`: migration `unsignedBigInteger()`, model/json integer casts, preview fallback warning for Resource field generation

## Nullability And Uniqueness

Fields are treated as required when `required: true` or a validation rule contains `required`.
Otherwise the migration preview appends `nullable()`.

The preview migration appends `unique()` when any validation rule starts with `unique`.

## Defaults

Migration preview columns append `default(...)` when the field definition contains a `default` key.

## Current Limits

This is still preview-only. Generated files are meant to be inspectable and structurally meaningful, not production-perfect.
Relationship field generation, select options, frontend form generation, and advanced table column behavior still need deeper first-party probes before real write mode.
