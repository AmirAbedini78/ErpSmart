# Builder Studio Visual Definition Editor

Status: MVP implementation note
Date: 2026-07-01

## Scope

This is the Builder Studio Visual Definition Editor MVP. It is still not the final drag/drop form builder.

The editor gives admins a UI-first way to edit the JSON module definition through structured controls while keeping raw JSON available for debugging and recovery.

## Principles

- Builder Studio remains UI-first.
- The existing Artisan command remains an engineering harness only.
- The visual editor edits the JSON definition safely.
- Raw JSON remains available for debugging.
- Visual editor changes update raw JSON.
- Applying raw JSON updates the visual editor.
- Publish is intentionally absent.
- No write-capable generation is exposed.
- No ERP packs/presets are introduced.
- No fixed business modules or fields are assumed.
- Existing Core theme components and layout patterns are reused.
- No global RTL changes are made.

## Implemented MVP Sections

Module Identity:

- module name
- namespace
- singular/plural labels
- table
- route/resource names
- icon
- model class
- title/order fields
- detail view and global search action

Fields:

- list fields
- add field with neutral placeholder names
- duplicate field
- remove field
- edit name/type/label/default/rules/visibility/table metadata

Capabilities:

- data/UI toggles
- collaboration/content toggles
- future/warning-only capability toggles
- form/layout toggles

Relations:

- list relations
- add/remove relations
- edit relation type, target, keys, display field, delete behavior, and generation flags
- future relation types are labeled as future/preview warning only

Raw JSON:

- raw JSON textarea
- Apply Raw JSON
- Format JSON
- parse error display

Validation/Preview:

- save
- validate
- preview
- status
- validation report
- preview output/manifest

## Synchronization Model

The Builder Definition detail view owns two synchronized values:

- `definitionJson`: the visual editor object
- `definitionText`: the raw JSON text

Visual component changes normalize the object and rewrite `definitionText`.

Apply Raw JSON parses `definitionText`, normalizes the object, and updates `definitionJson`.

Invalid raw JSON is not applied and does not silently discard the user input.

## Future Steps

- visual form layout builder
- stepper builder
- relation picker against existing/generated modules
- workflow/email/task builder
- capability-aware preview file browser
- generated verifier viewer
- AI prompt assistant
- queued job progress UI
- publish/rollback workflow after safety contracts are ready
