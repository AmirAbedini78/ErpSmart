# ERPSMART Builder Publish Execution Record And Lock MVP

Date: 2026-07-02

Status: implemented as a control-plane preparation record only. Real publish is still not implemented.

## What This Implements

The Builder now has a publish execution preparation record flow. An explicit UI/API action can create a control-plane record, acquire a publish preparation lock, run approved candidate preflight, write a rollback manifest draft under storage, create a storage-only staging root, write audit events, and release the lock.

## What This Does Not Implement

This does not publish. It does not write runtime module files, copy dry-run/candidate artifacts into runtime paths, run generated migrations, register runtime routes, mark a module as published, create a real module, or execute rollback.

## Flow

1. Create `builder_publish_executions` row with status `requested`.
2. Write `publish_preflight_started` audit event.
3. Acquire `builder:publish:{definition_id}` preparation lock.
4. Mark status `lock_acquired` and audit `publish_lock_acquired`.
5. Run approved candidate preflight.
6. If preflight fails, mark `preflight_failed`, store the report, audit failure, release lock, and audit release.
7. If preflight passes, create:
   - `storage/app/builder-publish-staging/{definition_id}/{execution_id}`
   - `storage/app/builder-publish-rollbacks/{definition_id}/{execution_id}/rollback-manifest.json`
8. Mark `preflight_passed`, store paths/report, audit rollback manifest and staging root creation, release lock, and audit release.

## Audit Events

Implemented preparation events:

- `publish_preflight_started`
- `publish_preflight_failed`
- `publish_lock_acquired`
- `publish_lock_failed`
- `publish_lock_released`
- `rollback_manifest_created`
- `publish_staging_created`

## Known Limitations

The rollback manifest is a draft. It records no runtime file backups because runtime writes are not allowed. Staging root exists under storage only and is not used to install code. Cancellation is listed as a future next allowed action but no cancel API is implemented yet.

## Next Steps

- staged file validation MVP
- runtime file write phase planning
- generated migration execution planning
- rollback execution planning
- stricter permission policy for publish execution records
