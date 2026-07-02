# Builder Publish Candidate Snapshot

Status: implemented MVP
Date: 2026-07-02

## Purpose

The publish candidate snapshot freezes the current Builder definition, publish readiness report, dry-run output, and dry-run review metadata into a derived JSON artifact for human review, audit, and AI/RAG reasoning.

## Boundaries

This is not publish and not approval-to-publish. It does not write runtime module files, copy dry-run files into runtime paths, run migrations, create real modules, register runtime routes, or change definition status.

The snapshot is written only under `storage/app/builder-publish-candidates/{definition_id}/{candidate_id}/candidate-snapshot.json`.

## Flow

Readiness analysis checks impact and conflicts. Publish dry-run generates sandbox artifacts under storage. Dry-run review explains artifacts and checklist items. Candidate snapshot freezes those derived reports into one review artifact. A future approval workflow would require persistent approvals, permissions, audit logs, signed manifests, transaction planning, rollback manifests, and post-publish smoke checks.

## AI/RAG Use

AI Builder Agent may create and summarize candidate snapshots. It may not approve publish, execute publish, copy candidate or dry-run artifacts into runtime paths, run migrations, or treat candidate snapshots as runtime source of truth.

## Future Steps

- approval request model
- human approval workflow
- publish execution transaction
- rollback strategy
