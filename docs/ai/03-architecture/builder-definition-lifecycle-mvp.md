# Builder Definition Lifecycle MVP

Status: implemented MVP
Date: 2026-07-02

## Scope

This lifecycle MVP is control-plane-only. It adds safe operations for Builder definitions:

- archive definition
- restore archived definition
- delete draft/unpublished definition

It does not publish modules, uninstall modules, remove runtime files, drop tables, run migrations, roll back published modules, or remove generated capabilities.

## Archive

Archive is allowed for unpublished Builder definitions. It sets `status = archived`, keeps definition records, keeps version records, keeps preview runs, and does not delete preview files.

Archive is useful when a draft should leave the active list while retaining audit and recovery context.

## Restore

Restore is allowed only for archived Builder definitions. It restores status to `draft`.

Restore does not change runtime modules, generated files, migrations, routes, menus, permissions, or business data.

## Delete Draft

Delete draft is allowed only for unpublished statuses:

- `draft`
- `validated`
- `validation_failed`
- `previewed`
- `preview_failed`
- `archived`

Delete draft removes only Builder control-plane records:

- `builder_definition_versions`
- `builder_preview_runs`
- `builder_definitions`

It does not delete shared preview files in this MVP because preview artifact ownership and cleanup policy need a separate safe cleanup task.

## Forbidden Runtime Effects

This MVP must not:

- delete runtime module files
- uninstall published modules
- drop tables
- run migrations
- remove generated code
- remove media/files
- remove business records
- publish or rollback modules

## Why Draft Delete Is Allowed

Draft Builder definitions are control-plane metadata. If a definition has never been published, deleting it does not remove runtime module code or business tables. The delete operation is still guarded by status checks so future published/disabled/uninstall/rollback states cannot be deleted through this MVP endpoint.

## Next Lifecycle Steps

Future lifecycle tasks should be separate and safety-critical:

- disable published module
- hide existing module through approved gates
- archive/delete preview artifacts with ownership checks
- uninstall generated module after dependency analysis, backups, retention rules, and rollback
- remove capabilities after artifact/data dependency checks
