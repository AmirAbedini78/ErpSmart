# Builder Studio UI Shell

Status: implementation note
Date: 2026-07-01

## Direction

Builder Studio is the main advanced UI-first Builder surface for ERPSMART software customization. Embedded Settings/Super Admin customization is a quick access path.

Both use the same backend Builder Control Plane. The CLI remains an engineering harness only.

SaaS integration is deferred. Publish/write-capable generation is deferred. ERP packs/presets are not part of this shell.

## Current Scope

The MVP shell supports:

- list builder definitions
- create a neutral draft
- open definition detail
- edit raw JSON definition temporarily
- save draft JSON
- validate definition
- preview definition
- show validation report
- show preview output/manifest

This is not the final visual builder. The raw JSON editor exists only to make the backend Control Plane usable from UI while visual editing is designed.

## Routes

Implemented UI routes:

- `/builder`
- `/builder/definitions`
- `/builder/definitions/:id`
- `/settings/software-customization`

The settings route redirects to Builder Studio and acts as the first embedded Super Admin/Settings entrypoint. A visible Settings menu item should be added later through a Builder provider/menu registration task.

## Backend API

The UI shell calls:

- `GET /api/builder/definitions`
- `POST /api/builder/definitions`
- `GET /api/builder/definitions/{id}`
- `PUT /api/builder/definitions/{id}`
- `POST /api/builder/definitions/{id}/validate`
- `POST /api/builder/definitions/{id}/preview`

No publish API is called or exposed.

## Future Scope

Future Builder Studio work:

- visual field builder
- relation builder
- capability toggles
- form layout builder
- stepper builder
- conditional visibility builder
- generated verifier viewer
- preview file browser
- AI prompt assistant
- queued status progress
- publish/rollback flow after safety contracts are ready

## Safety

The UI shell does not generate real runtime modules. It only calls draft, validate, and preview endpoints. Preview artifacts remain under the backend preview sandbox.
