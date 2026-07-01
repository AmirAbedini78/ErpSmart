# Module Builder Definition-Driven Core

Status: repair batch
Date: 2026-07-01

## Principle

The Module Builder core is raw and definition-driven. It must not assume fixed ERP modules such as Warehouse, Product, Inventory, Invoice, or any future preset.

The builder receives a JSON definition and renders preview files from that definition. Example JSON files are test fixtures only. They are not defaults, presets, or business-module packs.

## Why ERP Packs Come Later

ERP packs are opinionated collections of modules, fields, relations, workflows, and settings. They are useful later, but adding them to the core builder would make the generator less predictable and would risk hiding business assumptions inside generic infrastructure.

The core builder must first prove that it can generate arbitrary modules from explicit definitions.

## Module vs Capability

A module/entity is the generated business or operational record type:

- module name
- namespace
- route/resource name
- table
- fields
- relations
- labels

A capability is a reusable platform feature attached to that entity only when enabled:

- table/index behavior
- detail page
- import/export
- clone
- notes
- activities
- media
- global search
- quick create

Capabilities must never inject business fields. They may add platform fields only when required, such as `import_id` when `importable` is enabled.

## Capability Groups

Data/UI capabilities:

- `tableable`
- `hasDetailView`
- `customFields`
- `uniqueCustomFields`
- `importable`
- `exportable`
- `cloneable`
- `bulkDelete`
- `globalSearch`
- `quickCreate`
- `softDeletes`

Collaboration/content capabilities:

- `notes`
- `comments`
- `activities`
- `activityComments`
- `activityAssociations`
- `media`
- `documents`
- `calls`
- `emails`
- `emailSending`

Automation/process capabilities:

- `tasks`
- `workflow`
- `approvals`
- `notifications`
- `timeline`

Form/layout capabilities:

- `formLayout`
- `stepperForm`
- `sections`
- `conditionalVisibility`

Unsupported or future capabilities may exist in the schema, but the preview renderer must warn instead of generating unsafe APIs.

## Fields

Generated Model fillable fields, migration columns, Resource fields, and JsonResource output come only from `definition.fields`.

No default `name`, `code`, `sku`, `price`, `stock`, Warehouse, Product, Inventory, or Invoice assumptions may be injected by the renderer.

## Relations

Relations are explicit. The preferred contract uses:

- `name`
- `type`
- `targetModule`
- `targetModel`
- `targetResource`
- `localKey`
- `foreignKey`
- `displayField`
- `required`
- `nullable`
- `onDelete`
- `showOnDetail`
- `showOnIndex`
- `generateField`
- `generateMigrationColumn`
- `generateFrontendPanel`

MVP preview rendering supports `belongsTo` and `hasMany` model methods.
Future relation types are present in the schema but should not generate unsafe code until their first-party contracts are proven.

## AI Agent Implication

The future AI Agent should generate module definitions first. The builder should then render from those definitions. This keeps AI work auditable: fields, relations, capabilities, permissions, and labels are explicit JSON inputs rather than hidden generator assumptions.
