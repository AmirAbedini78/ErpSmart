# ERPSMART Builder Publish Execution Architecture

Date: 2026-07-02

Status: planning only. Real publish is not implemented by this document.

## Purpose

Builder publish must be a separate, explicit, human-confirmed operation. The current Builder Control Plane can validate definitions, render previews, analyze publish readiness, generate sandbox dry-runs, create candidate snapshots, record human approval requests, and run approved candidate preflight. None of those operations publishes a module.

Future publish is the first point where ERPSMART may write runtime module files, run generated migrations, register routes, update menus and permissions, clear caches, and create a rollback point. That makes it safety-critical and separate from preview, approval, and preflight.

## Future Pipeline Phases

1. Receive explicit human publish command.
2. Verify user permission.
3. Acquire publish lock.
4. Load BuilderDefinition.
5. Run approved candidate preflight.
6. Verify approved candidate is current.
7. Verify approval still approved.
8. Verify candidate snapshot path exists.
9. Verify dry-run manifest exists.
10. Re-run readiness analyzer or compare checksum.
11. Build final publish execution plan.
12. Create rollback manifest before writes.
13. Stage runtime files in temporary staging directory.
14. Validate staged files.
15. Backup existing target files.
16. Write runtime files atomically where possible.
17. Prepare DB migration plan.
18. Run migrations only in explicit future publish mode.
19. Register routes/menus/permissions.
20. Clear relevant caches.
21. Run post-publish smoke tests.
22. Mark publish result.
23. Write audit events.
24. Store final publish manifest.
25. Provide rollback entry point.

## Separation From Existing Safe Steps

Approval does not publish. Approval records human review state for a candidate snapshot and checksum.

Approved candidate preflight does not publish. It confirms that an approved candidate appears current and eligible for a future explicit publish command.

Dry-run does not publish. It writes sandbox artifacts under storage for review only.

Candidate snapshot does not publish. It freezes derived review artifacts under storage for audit and later approval checks.

## Final Human Confirmation

Future publish must require a dedicated human final confirmation after approved candidate preflight passes. A prior approval request is not enough by itself, because state can change between approval and execution. The final command must be explicit, permissioned, locked, audited, and tied to a current candidate checksum.

## AI Agent Boundary

The AI Builder Agent may summarize publish plans, explain preflight blockers, and recommend next safe review steps. It must never execute publish autonomously, acquire publish locks, write runtime files, run migrations, or execute rollback.

## Current MVP Boundary

The current MVP remains preview, readiness, dry-run, candidate snapshot, approval request, and approved candidate preflight only. Real publish, runtime writes, migration execution, route registration, and rollback execution remain forbidden until a separate implementation task adds the pipeline with tests, permissions, locks, audit, and rollback manifests.
