# Builder Studio End-to-End Smoke

Status: implementation smoke note
Date: 2026-07-01

## Purpose

The Builder Studio end-to-end smoke proves that the UI-first Builder backend Control Plane is usable for the demo path before work moves to the Visual Form Layout Builder.

It verifies database tables, model relationships, version snapshots, validation, preview rendering, preview run recording, output safety, and route/UI assumptions.

## What It Verifies

- Builder tables exist.
- A temporary Builder definition can be created.
- Version 1 can be created.
- The definition can be validated.
- Preview can be generated through `BuilderPreviewService`.
- A preview run is recorded.
- Preview output contains `Real runtime writes performed: 0`.
- Preview path stays under `storage/app/module-builder-preview`.
- No real module directory is created under `modules/`.
- Temporary smoke DB records are cleaned up.
- Builder routes exist.
- UI still exposes Save, Validate, and Preview while Publish remains absent.

## What It Does Not Test Yet

- Browser interaction with the visual editor.
- Queued preview execution.
- Preview file browsing.
- Generated verifier execution inside the UI.
- Publish or rollback.
- Runtime module migrations.

## Why Publish Remains Absent

Publish is a separate safety boundary. It requires a publish manifest, rollback manifest, generated verifier gate, permissions, queue boundaries, migration safety, and file ownership strategy. This smoke intentionally proves only draft, validate, and preview.

## Manual UI Test

1. Open `/builder/definitions`.
2. Create a draft.
3. Edit module identity, fields, capabilities, and relations.
4. Confirm raw JSON stays synchronized.
5. Save.
6. Validate and confirm the report is visible.
7. Preview and confirm output is visible.
8. Confirm no real runtime module was created.

## Known Build Warnings

Non-blocking warnings currently seen during `npm run build`:

- Sass legacy JS API deprecation warning.
- Tailwind content configuration warning.
- Runtime font paths that remain unresolved at build time.
- Large app chunk warning.

These warnings are not blockers for this smoke because the production build completes successfully. A future frontend cleanup task can address them separately.

## Next Recommendation

After this smoke passes, the next safe task is the Visual Form Layout Builder MVP. It should remain definition-driven and preview-first, with publish still absent.
