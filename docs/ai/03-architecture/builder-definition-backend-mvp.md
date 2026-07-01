# Builder Definition Backend MVP

Status: implementation note
Date: 2026-07-01

## Purpose

This batch creates the first backend Control Plane foundation for the UI-first ERPSMART Module Builder. It is intended for future Builder Studio and embedded Super Admin/Settings customization screens.

The current Artisan command remains an engineering harness only. UI, AI Agent, and future automation should call backend Control Plane APIs rather than shelling out directly.

## Scope

Implemented backend-only MVP pieces:

- `builder_definitions`
- `builder_definition_versions`
- `builder_preview_runs`
- BuilderDefinition, BuilderDefinitionVersion, BuilderPreviewRun models
- structural definition validator
- definition version service
- safe preview service
- admin-scoped Builder Definition API endpoints

Not implemented:

- full UI
- real publish
- write-capable module generation
- generated module migrations
- ERP packs/presets
- fixed business module assumptions

## Storage Model

Definitions are versioned JSON, not sole source of runtime truth.

`builder_definitions` stores the editable current definition, status, checksum, and last validation/preview reports.

`builder_definition_versions` stores immutable snapshots and reports. This gives future publish/rollback work a durable audit layer.

`builder_preview_runs` stores preview execution status, preview path, manifest, output, and errors.

Published runtime business data must remain relational/published. Preview artifacts are disposable and can be regenerated from a definition version.

## API

Routes are registered under `auth:sanctum` and `admin` middleware:

- `GET /api/builder/definitions`
- `POST /api/builder/definitions`
- `GET /api/builder/definitions/{builderDefinition}`
- `PUT /api/builder/definitions/{builderDefinition}`
- `POST /api/builder/definitions/{builderDefinition}/validate`
- `POST /api/builder/definitions/{builderDefinition}/preview`

These routes do not expose publish.

## Validation

`BuilderDefinitionValidator` loads `docs/ai/05-rag/contracts/module-builder-mvp-schema.json` when available and performs structural validation without external composer packages.

It checks required module/resource/fields/capabilities/frontend/verifier keys, rejects pack schema, and returns:

- `valid`
- `errors`
- `warnings`

Unsupported future capabilities return warnings rather than unsafe generated code.

## Preview

`BuilderPreviewService` writes a temporary definition file under:

`storage/app/builder-definitions/{id}/definition.json`

It then calls the existing engineering harness:

`php artisan erpsmart:make-module --definition=... --preview`

Preview output is recorded in `builder_preview_runs`. Preview artifacts must remain under:

`storage/app/module-builder-preview`

The service does not publish, does not copy files into real modules, and does not run generated migrations.

## Queue Readiness

Preview is synchronous in this MVP to keep the backend small and verifiable. The controller/service boundary is intentionally easy to move behind queued jobs later:

- validate definition job
- render preview job
- run verifier job
- publish job
- rollback job

Heavy generation should not remain in request lifecycle for market-ready Builder.

## Future UI

Builder Studio and embedded Settings/Super Admin customization should use the same Control Plane API.

Suggested UI flows:

- create draft
- autosave definition
- validate
- render preview
- inspect manifest/output
- request publish later
- rollback later

## Future AI Agent

The AI Builder Agent should create or update definitions through the same API. It must not write runtime source directly. It can request validation and preview, then propose fixes based on structured reports.
