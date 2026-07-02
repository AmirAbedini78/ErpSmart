# Builder Human Approval Request MVP

Status: implemented MVP
Date: 2026-07-02

## Purpose

The approval request MVP persists human review state for Builder publish candidate snapshots. It supports requesting approval, approving a candidate, rejecting a candidate, revoking approval, checksum invalidation, and append-only audit events.

Approval does not publish. Approval does not copy artifacts into runtime paths. Approval does not run generated migrations, register runtime routes, write runtime modules, create real modules, or execute any publish action.

## Binding

Each approval request binds to:

- `builder_definition_id`
- `candidate_id`
- `definition_checksum`
- `candidate_snapshot_path`
- `candidate_root`
- stored `snapshot_json`

If the Builder definition checksum changes before approval, approval is invalidated and an `approval_invalidated` audit event is written.

## Audit Events

Implemented events:

- `approval_requested`
- `approval_approved`
- `approval_rejected`
- `approval_revoked`
- `approval_invalidated`

Audit logs are append-only by convention. No update/delete APIs are exposed for audit logs.

## Limitations

- Approval expiry is schema-ready but not enforced yet.
- Fine-grained approval permissions are not implemented yet beyond current admin routes.
- Approval does not unlock publish.
- Future publish preflight must require approved current candidate and still rerun validation/readiness/dry-run checks.

## Next Steps

- approval expiry enforcement
- permission granularity
- publish preflight requiring approved current candidate
- rollback approval
