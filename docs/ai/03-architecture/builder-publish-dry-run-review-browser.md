# Builder Publish Dry-Run Review Browser

Status: implemented MVP
Date: 2026-07-02

## Purpose

The dry-run review browser helps admins inspect sandboxed publish dry-run artifacts before any real publish workflow exists. It shows generated artifact paths, future runtime path mappings, safety checks, approval checklist items, forbidden actions, and next allowed actions.

## Boundaries

The review browser is informational only:

- no publish
- no approval persistence
- no approval workflow
- no copy-to-runtime action
- no migration execution
- no runtime route registration
- no module creation

Dry-run artifacts remain derived review artifacts under `storage/app/builder-publish-dry-runs`.

## Approval Checklist

The approval checklist is not approval state. It documents what a future publish gate must check before a publish implementation can be considered safe:

- validation passed
- runtime writes are zero
- no migrations were run
- no runtime routes were registered
- readiness analyzer completed
- dry-run manifest is valid
- blockers are empty
- unsupported capabilities reviewed
- form layout metadata reviewed
- automation metadata reviewed
- rollback requirements reviewed
- human approval required before future publish

## Future Approval Gate

A future approval gate needs persistent approval records, permissions, audit logs, signed publish manifests, rollback manifests, dependency checks, generated verifier results, and explicit human confirmation. None of that is implemented here.

## AI/RAG Use

AI Builder Agent may summarize dry-run review findings and explain checklist items. It may not approve publish, execute publish, or copy dry-run artifacts into runtime paths.
