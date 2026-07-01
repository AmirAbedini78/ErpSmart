# Builder Definition Backend MVP

Date: 2026-07-01

## Summary

Added the first backend Control Plane foundation for the UI-first Module Builder.

## Added

- Builder definition, version, and preview run migrations
- BuilderDefinition, BuilderDefinitionVersion, and BuilderPreviewRun models
- BuilderDefinitionValidator
- BuilderDefinitionVersionService
- BuilderPreviewService
- BuilderDefinitionController
- Store/Update Builder Definition requests
- Admin-scoped Builder Definition API routes
- Backend MVP architecture note
- Backend MVP verifier

## Safety

No publish endpoint was added. Preview remains preview-only and writes artifacts only under `storage/app/module-builder-preview`.

The existing Artisan command remains an engineering harness only.
