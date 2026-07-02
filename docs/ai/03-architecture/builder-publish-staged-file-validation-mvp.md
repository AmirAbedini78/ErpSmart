# ERPSMART Builder Publish Staged File Validation MVP

Date: 2026-07-02

Status: implemented as a control-plane-only validation phase. Real publish remains forbidden.

## Purpose

Staged file validation checks storage-only artifacts created by publish execution preparation before any future runtime write phase exists. It verifies paths, checksums, classifications, manifests, and forbidden path policy while keeping runtime writes at zero.

## Scope

The validator reads only the execution staging root and rollback manifest root for one `BuilderPublishExecution`. It writes one validation report under:

`storage/app/builder-publish-staged-validations/{definition_id}/{execution_id}/staged-file-validation.json`

It updates the execution record status to `staging_validated` or `staging_validation_failed` and stores the report path/report in `metadata_json`.

## Safety Boundaries

Staged validation does not publish, copy files to runtime, run generated migrations, register routes, mark modules as published, create real modules, or execute rollback.

Forbidden path patterns include:

- `modules/`
- `database/migrations/`
- `routes/`
- `resources/js/app.js`
- `public/build/`
- `vendor/`
- `node_modules/`
- `.env`
- storage paths outside the allowed Builder publish staging, rollback, and validation scopes

## Checksums And Classification

Each discovered file receives size, extension, SHA-256 checksum, scope, and classification. Known classifications include rollback manifest, manifest, migration stub, model stub, route stub, PHP stub, Vue stub, documentation, and unknown.

## Path Traversal Defense

The validator resolves real paths and requires every discovered file to remain inside the expected storage root. Relative paths containing traversal markers or forbidden runtime-like roots block validation.

## Future Use

This phase supports a future runtime write phase by proving the planned artifacts are inspectable and traceable. A future publish executor must still require explicit human confirmation, approved candidate preflight, locks, rollback manifest, staged validation, audit, and post-publish smoke.

AI Builder Agent may summarize staged validation results when human-initiated, but it must not copy artifacts, execute publish, or treat validation as publish.
