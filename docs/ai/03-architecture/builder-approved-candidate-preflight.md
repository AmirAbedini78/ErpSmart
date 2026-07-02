# Builder Approved Candidate Preflight

Status: implemented MVP
Date: 2026-07-02

## Purpose

Approved candidate preflight is a read-only control-plane check that determines whether a Builder definition has a current approved candidate that could be eligible for future publish.

Preflight is not approval and not publish. Approval records human review state. Preflight checks freshness and safety. Future publish would still need a separate execution architecture, transaction plan, rollback manifest, and human final confirmation.

## Why An Approved Candidate Can Still Be Blocked

An approved candidate becomes stale if the definition checksum changes, the candidate snapshot file disappears, the snapshot JSON is invalid, the snapshot or dry-run reports runtime writes, or the readiness report indicates publish was executed.

## Checks

The preflight checks:

- approved request exists
- approval status is approved
- approval was not revoked, rejected, invalidated, or expired
- definition checksum matches
- candidate id exists
- candidate snapshot path exists
- candidate snapshot JSON is valid
- candidate snapshot publish flag is false
- candidate snapshot runtime writes are zero
- dry-run runtime writes are zero when present
- readiness publish flag is false when present
- approval does not publish
- future publish is still forbidden

## Boundaries

The preflight service performs no database writes, creates no audit logs, changes no approval status, writes no runtime files, copies no artifacts, runs no migrations, and creates no real modules.

## Next Steps

- publish execution architecture
- publish transaction plan
- rollback manifest
- human final confirmation
