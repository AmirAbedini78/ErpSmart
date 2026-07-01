# Builder Studio Demo Workflow

Status: demo workflow note
Date: 2026-07-01

## Purpose

Builder Studio is the UI-first control surface for ERPSMART module definition work. The current Artisan command remains an engineering harness only.

This demo workflow proves that an admin can create a draft definition, edit it visually, save it, validate it, and render a sandbox preview without publishing or writing real runtime modules.

## Implemented

- Builder definitions index
- Create neutral draft
- Open definition detail
- Visual module identity editor
- Visual fields editor
- Visual capability toggles
- Visual relations editor
- Raw JSON editor for debugging and recovery
- Save draft
- Validate definition through the backend Control Plane
- Preview definition through the backend Control Plane
- Validation report and preview output display
- Preview artifacts restricted to `storage/app/module-builder-preview`

## Intentionally Missing

- Publish
- Write-capable runtime module generation
- Runtime migrations from Builder UI
- ERP packs or presets
- Fixed business module assumptions
- Full drag/drop form builder
- Full RTL conversion
- SaaS integration

## UI And Backend Flow

Builder Studio uses the same backend Builder Control Plane that future embedded Settings/Super Admin customization and AI Builder Agent flows should use.

The UI calls:

- `GET /api/builder/definitions`
- `POST /api/builder/definitions`
- `GET /api/builder/definitions/{id}`
- `PUT /api/builder/definitions/{id}`
- `POST /api/builder/definitions/{id}/validate`
- `POST /api/builder/definitions/{id}/preview`

No publish endpoint is exposed or called.

## Preview Is Not Publish

Preview renders derived artifacts into the preview sandbox. It does not copy files into `modules/{Module}`, does not run generated migrations, and does not mutate Core, Warehouse, SaaS, licensing, updater, package, composer, vendor, or build files.

Publish will need a separate control-plane phase with explicit manifests, verification, rollback, permissions, and queue boundaries.

## Manual Smoke Test

1. Run pending migrations for Builder tables.
2. Open Builder Studio at `/builder/definitions`.
3. Create a draft.
4. Edit identity, fields, capabilities, and relations visually.
5. Confirm raw JSON updates.
6. Save.
7. Validate.
8. Preview.
9. Confirm preview output is visible.
10. Confirm no real runtime module directory was created.

## Build Command

Run:

```bash
docker compose exec node npm run build
```

## Known Limitations

- Raw JSON remains available because the visual builder is still MVP.
- Preview runs synchronously through the current backend service.
- Preview output is text/manifest oriented, not a full file browser yet.
- Future/warning-only capabilities are visible but should not generate unsafe APIs.
- No publish/rollback UI exists yet.

## Next Steps

- Add queued validation/preview progress.
- Add preview file browser.
- Add generated verifier viewer.
- Add relation picker against known Builder definitions.
- Add form layout and stepper builders.
- Add AI prompt assistant that proposes definition changes but still uses validate and preview.
- Add publish/rollback only after the safety contract is complete.
