# Warehouse Domain Notes

## Current module status

Warehouse is being built as the canonical ERP template module.

Implemented capabilities:

- CRUD
- Resource UI
- Permissions
- Boolean normalization
- Custom fields entry
- Import / Export
- Clone / Delete actions
- Offline Vue runtime fix
- Notes integration
- Generic Media / Attachments integration step

## Notes integration stable contract

- Warehouse model uses `HasTimeline`.
- Warehouse model exposes `notes()`.
- Notes model must expose concrete `warehouses()` method.
- `noteables` pivot has no timestamps.
- Warehouse detail view must provide `resource.path`.

## Media / Attachments integration contract

Warehouse file attachments use the Core media pipeline:

- Resource implements `Mediable`.
- Model uses `HasMedia`.
- UI uses `ResourceMediaPanel` / `resource-media-panel`.
- This is for generic files, photos, permits, receipts and internal documents.

CRM Documents are separate and should only be used when Warehouse must generate/send/sign/track formal documents.
