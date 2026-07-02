# Builder Human Approval Gate

Status: planning only
Date: 2026-07-02

## Purpose

The human approval gate is a future control-plane boundary between a reviewed publish candidate snapshot and any publish execution. It must make clear who requested approval, who approved or rejected it, exactly what artifact was approved, and which later changes invalidate that approval.

Approval is separate from candidate snapshot creation. Approval is also separate from publish execution. An approved candidate is still not published; future publish must re-run preflight checks before any runtime write.

## Approval Binds To

Future approval records must bind to:

- `builder_definition_id`
- `candidate_id`
- `definition_checksum`
- `candidate_snapshot_path`
- `dry_run_root`
- readiness report checksum if available
- `requested_by`
- `approved_by`, `rejected_by`, or `revoked_by`
- approval timestamp and expiration timestamp

## Future Statuses

- `not_requested`
- `requested`
- `approved`
- `rejected`
- `revoked`
- `expired`
- `invalidated`

## Invalidation Rules

Approval must be invalidated when:

- `definition_json` changes
- definition checksum changes
- candidate snapshot is regenerated
- dry-run is regenerated after request
- a blocker appears
- an unsupported capability appears
- approval expires

## Permission Model

Approval request and approval decisions should require explicit Builder administration permissions. Requesting approval, approving, rejecting, revoking, and publishing should be separate permissions. The same actor should not be allowed to self-approve unless a future policy explicitly permits it.

## Publish Relationship

Approval must not equal publish. Future publish must require a fresh approval, but still run validation, readiness analysis, dry-run checks, dependency checks, backup checks, and verifier checks before any runtime writes.

## AI Boundary

AI Builder Agent may summarize approval requirements and explain why an approval is invalidated. It may not request approval until an implementation exists, may not approve, reject, revoke, or execute publish.
