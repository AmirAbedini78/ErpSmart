# Builder Studio Form Layout Builder

Status: MVP implementation note
Date: 2026-07-01

## Scope

This is the first Builder Studio Visual Form Layout Builder MVP. It is not the final drag/drop builder.

The feature lets admins edit form layout metadata inside `definition_json.formLayout` using simple controls: sections, columns, field assignment, ordering, mode visibility, stepper draft metadata, and conditional visibility draft metadata.

## Principles

- Builder Studio remains UI-first.
- Raw JSON remains available as the debug and recovery view.
- Form layout metadata is stored in the Builder definition JSON.
- No runtime form renderer is implemented in this batch.
- No publish or write-capable generation is implemented.
- No ERP packs or fixed business modules are introduced.
- No global RTL changes are made.
- Sections and columns use logical layout concepts, not hard-coded left/right assumptions.

## Metadata

The current metadata root is:

```json
{
  "formLayout": {
    "enabled": true,
    "mode": "standard",
    "sections": [],
    "stepper": {
      "enabled": false,
      "steps": []
    },
    "conditions": []
  }
}
```

The UI edits and normalizes this shape only. The preview command may not consume it yet.

## Implemented MVP Controls

- Enable form layout metadata.
- Select standard or stepper mode.
- Add, remove, edit, and move sections.
- Configure section id, label, description, columns, modes, and order.
- Assign fields from `definition.fields`.
- Move fields up/down inside sections.
- Configure field width, required override, readonly modes, hidden modes, and help text.
- Configure stepper draft steps and section assignments.
- Configure conditional visibility draft rules.

## Runtime Boundary

The future renderer will translate this metadata into Core field/detail/create/update form structures. That renderer is intentionally absent here.

The Builder Agent may propose layout changes by editing `definition_json.formLayout`, then using validate and preview. It must not write runtime module files or bypass the Builder Control Plane.

## Future Work

- Visual drag/drop interactions.
- Field palette and layout preview.
- Runtime renderer for Core forms and detail panels.
- Stepper runtime renderer.
- Conditional visibility evaluator.
- Generated verifier coverage for published form layouts.
