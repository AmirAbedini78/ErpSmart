# Builder UI Entrypoint Options

Status: architecture probe
Date: 2026-07-01

## Option 1: Only Builder Studio

Pros:

- clean advanced workspace
- easier to expose preview/validation/publish lifecycle
- good for developers/admin power users

Cons:

- too heavy for simple admin tasks
- Super Admin users may expect customization near Settings
- quick field/form changes become context switches

## Option 2: Only Embedded Super Admin/Settings Customization

Pros:

- familiar admin placement
- good for add field, edit form, enable capability, and minor module changes
- aligns with current settings menu and `meta.superAdmin` patterns

Cons:

- too cramped for multi-step module design
- harder to show diffs, generated files, verifier output, and rollback plans
- advanced Builder work may become scattered

## Option 3: Hybrid Model

Pros:

- Builder Studio supports advanced work.
- Embedded Super Admin/Settings customization supports quick changes.
- Both use the same backend Builder Control Plane.
- Permissioning, audit, validation, preview, publish, and rollback stay consistent.

Cons:

- requires careful navigation and state design
- needs shared UI components for definitions, fields, relations, capabilities, validation, and preview status

## Recommendation

Use the hybrid model.

Builder Studio should be the full advanced workspace. Embedded Settings/Super Admin entrypoints should expose quick customization flows and link into Builder Studio when the change becomes complex.

The same backend Builder Control Plane must serve both UI surfaces. The CLI remains engineering harness only.
