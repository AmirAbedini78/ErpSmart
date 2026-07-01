# Module Builder UI Control Plane

Status: architecture probe
Date: 2026-07-01

## Product Direction

The final Builder must be UI-first Builder, not CLI-first. The existing Artisan command is an engineering preview/verifier harness only.

ERPSMART should provide two UI surfaces:

- Builder Studio for advanced software customization.
- Embedded Super Admin/Settings customization for quick changes such as add module, edit module, add field, edit form, configure relations, and enable/disable capabilities.

Both surfaces must use the same backend Builder Control Plane.

## Product Pattern Research Summary

Known products show useful patterns, but ERPSMART should not copy any product:

- Odoo Studio: integrated in-app app/form customization.
- ERPNext/Frappe: Customize Form, Custom Field, and DocType-style metadata.
- ProcessMaker: workflow/form/process builder separation.
- Salesforce Lightning App Builder: admin setup and page/app customization.
- Zoho Creator: app/form builder for custom operational apps.

ERPSMART's own architecture should combine a full Builder Studio with embedded Settings/Super Admin entrypoints, backed by the same control-plane lifecycle and permission model.

## Backend Builder Control Plane

The Control Plane should expose APIs for:

- create/update draft definition
- validate definition
- render preview
- inspect preview manifest
- request publish
- inspect publish status
- rollback published version
- export definition/version
- inspect generated verifier results

The UI and future AI Builder Agent call these APIs. The CLI command remains only an engineering harness for local preview/verifier work.

## Lifecycle

Recommended lifecycle:

- `draft`
- `validating`
- `validated`
- `previewing`
- `previewed`
- `publish_pending`
- `publishing`
- `published`
- `publish_failed`
- `archived`
- `rolled_back`

Heavy transitions should dispatch jobs and update status asynchronously.

## Permission Model

Builder operations are admin/super-admin features. Source evidence from first-party settings routes shows settings UI routes can use `meta.superAdmin: true`, and Core admin APIs sit inside admin middleware groups.

Recommended permissions:

- view builder definitions
- create/edit builder drafts
- validate builder definitions
- render previews
- publish modules
- rollback modules
- manage Builder Studio

Publish and rollback should require the highest privilege tier and durable audit logs.

## Queue/Job Model

The Control Plane should not run heavy generation in request lifecycle. It should dispatch jobs for validation, preview, verifier execution, publish, migrations, cache rebuilds, and RAG index rebuilds.

Use status polling or websockets for UI progress.

## Preview Sandbox Model

Preview files remain under `storage/app/module-builder-preview/{Module}/` or object storage later. Preview writes are not runtime writes. Preview manifests should record:

- generated file list
- checksum
- definition version
- warnings
- verifier result

Preview cleanup should be scheduled and safe because the source of truth is the definition version.

## Safe Publish Model

Publish must:

- lock the definition/version
- verify checksum
- generate a publish manifest
- write files through a controlled writer
- run syntax checks
- run generated verifier
- run migrations only after approval
- rebuild menus/cache/resource metadata
- write audit events

Publish should have a rollback manifest before making runtime changes.

## Rollback Model

Rollback should use the publish manifest and rollback manifest. It should not infer rollback from current mutable JSON.

Rollback must be queued and audited. Destructive rollback, especially migrations and dropped columns, needs explicit confirmation and backups.

## Recommended UI Routes

Design-only routes:

- `/builder`
- `/builder/definitions`
- `/builder/definitions/:id`
- `/builder/preview/:id`
- `/settings/software-customization`
- `/settings/modules`
- `/settings/modules/:id/customize`

## Future AI Agent

The AI Builder Agent should generate and revise definitions, not write runtime files directly. It calls the same backend Builder Control Plane used by Builder Studio and embedded Super Admin/Settings customization.

Builder/code/customization RAG and Business Operations RAG must be separate so code-generation context does not mix with tenant business data.
