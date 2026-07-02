# Builder Publish Audit Log Strategy

Status: planning only
Date: 2026-07-02

## Purpose

Future publish, approval, and lifecycle flows need an append-only audit trail that records who did what, when, against which definition, candidate, checksum, and payload.

No audit persistence is implemented in this task. Future implementation should use a database table/model with immutable rows and strict retention rules.

## Required Future Events

- `candidate_snapshot_created`
- `approval_requested`
- `approval_approved`
- `approval_rejected`
- `approval_revoked`
- `approval_invalidated`
- `publish_preflight_started`
- `publish_preflight_failed`
- `publish_started`
- `publish_failed`
- `publish_succeeded`
- `rollback_started`
- `rollback_failed`
- `rollback_succeeded`
- `lifecycle_archived`
- `lifecycle_restored`
- `lifecycle_deleted_draft`

## Event Shape

Each event should store:

- actor id
- timestamp
- event type
- payload JSON
- builder definition id
- candidate id when applicable
- definition checksum
- candidate snapshot checksum when applicable
- request id/correlation id when available

## Boundaries

Audit logs are not source of truth for generated code. They are evidence and traceability records. Generated runtime files, publish manifests, migration state, and database records remain the future runtime source of truth after publish exists.
