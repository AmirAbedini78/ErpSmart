# ERPSMART Builder Publish Locking And Staging Strategy

Date: 2026-07-02

Status: planning only. Publish locks and staging execution are not implemented by this document.

## Purpose

Future Builder publish must prevent concurrent writes to the same definition, runtime module path, migrations, permissions, menus, and cache state. It must also validate generated files in a staging location before touching runtime paths.

## Lock Scopes

The future pipeline should use at least these lock scopes:

- Definition publish lock: one publish per Builder definition at a time.
- Runtime path lock: one writer for a module/runtime path at a time.
- Migration lock: one generated migration execution path at a time.
- Permission/menu lock: one registration update path at a time.

Lock backends can be Redis/cache locks or database locks. The chosen backend must work reliably in the deployment topology used by ERPSMART.

## Staging Path

Future staged files should be created under:

`storage/app/builder-publish-staging/{definition_id}/{publish_id}`

Staging is not runtime. Files in staging must not be loaded by the application, registered as routes, or treated as installed modules.

## Staging Validation

Before copying staged files to runtime paths, publish should validate:

- generated PHP syntax
- generated frontend syntax where possible
- manifest completeness
- expected file count
- target path mapping
- no writes outside allowed future publish scopes
- no stale checksum or candidate mismatch

## Atomic Write Considerations

Atomic rename is preferred where possible, but Docker bind mounts, WSL filesystems, and cross-device moves can make rename behavior inconsistent. The publish pipeline should be prepared to copy to a temporary file, verify checksum, then rename within the same directory when possible.

## Failure Cleanup

If staging fails, staged artifacts can be retained for diagnosis or cleaned according to retention policy. If runtime copy starts, rollback manifest and backups must already exist. Lock release must happen in `finally`-style cleanup and must be audited.

## Lock Expiry

Locks need a timeout and recovery policy. Expired locks should not be blindly ignored; publish should verify whether another process is still active and write an audit event before recovery.

## Audit Events

Future audit events should include lock acquired, lock failed, lock released, staging created, staging validated, runtime write started, runtime write failed, and runtime write succeeded.

## Current MVP Boundary

No publish lock is acquired in the current MVP. No staging directory is created by publish execution. The dry-run and candidate snapshot storage paths remain derived review artifacts, not runtime staging.
