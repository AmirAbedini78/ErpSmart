# ERPSMART Builder Runtime Write Phase Plan

Date: 2026-07-02

Status: planning only. Runtime writes are not implemented.

## Purpose

The runtime write phase is the future phase that may copy validated staged artifacts into approved runtime paths. It must remain separate from validation, preflight, staging, migration execution, route registration, smoke tests, publish finalization, and rollback execution.

## Future Phase Sequence

1. Receive explicit human final confirmation.
2. Load publish execution record.
3. Require execution status `staging_validated`.
4. Verify staged validation report still exists.
5. Verify approved candidate preflight is still current.
6. Acquire runtime path lock.
7. Build runtime write plan.
8. Validate every `future_runtime_path` against allowlist.
9. Create backups before overwrite.
10. Update rollback manifest with backup hashes and write plan.
11. Copy files atomically where possible.
12. Never run migrations in file write phase.
13. Never register routes in file write phase.
14. Write audit events.
15. Mark runtime write phase result, not final published state.
16. Require separate future smoke test phase before published status.

## Boundaries

Current implementation is `planning_only`. No runtime write endpoint exists. No copy-to-runtime endpoint exists. No publish endpoint exists. No UI action exists. Actual publish is still forbidden.

Runtime write is not publish. Even if a future runtime write phase succeeds, the module must not be marked published until separate smoke, route/menu/permission/cache, and publish-finalization gates pass.

## Migration And Route Handling

Generated migration files may be part of a future file plan, but this phase must not execute migrations. Runtime route files may be written only in a future allowed path strategy, but this phase must not register routes or clear route caches as publish.

## AI Agent Boundary

AI Builder Agent may summarize the runtime write plan. AI must not execute runtime write, override path allowlists, skip backups, use MCP to execute runtime write, or treat runtime write planning as publish.
