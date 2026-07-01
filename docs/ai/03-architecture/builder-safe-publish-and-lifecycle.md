# Builder Safe Publish And Lifecycle

Status: planning only
Date: 2026-07-02

## Purpose

Builder preview and Builder publish must remain separate operations. Preview renders disposable artifacts under `storage/app/module-builder-preview` and must not write runtime modules, run migrations, register routes, change menus, or modify live data. Publish is a future safety-critical workflow that changes the running application and therefore needs its own lifecycle, locks, manifests, backups, dependency analysis, and rollback point.

No publish, delete, uninstall, rollback, or runtime module removal is implemented in this task.

## Future Publish Phases

Future publish should be staged and auditable:

1. Validate the definition against schema, capability rules, naming rules, and unsupported feature warnings.
2. Run dependency impact analysis for routes, menus, policies, permissions, tables, relations, custom fields, media, notes, activities, automation metadata, RAG indexes, and generated assets.
3. Produce a dry-run file plan with exact creates, updates, deletes, checksums, and conflicts.
4. Produce a DB migration plan with reversible and destructive operations clearly separated.
5. Produce a permission, menu, route, cache, search, and frontend registration plan.
6. Create backups and snapshots for definition versions, publish manifests, generated files, database schema/data where needed, and rollback manifests.
7. Execute publish as a transaction or staged publish with locks, checkpoints, and failure handling.
8. Run post-publish smoke checks for routes, resource metadata, policies, menus, migrations, preview/verifier output, and basic UI entry.
9. Store a rollback point that does not rely on mutable current JSON.

## Lifecycle States

The future lifecycle should support these states:

- `draft`
- `validated`
- `previewed`
- `publish_ready`
- `published`
- `disabled`
- `archived`
- `uninstall_planned`
- `uninstalling`
- `uninstalled`
- `rollback_planned`
- `rolled_back`
- `failed`

The current MVP should only execute draft, validate, and preview style operations. Published, disabled, uninstall, and rollback states are planning targets only until a separate implementation task adds safety contracts, permissions, jobs, and verifier coverage.

## Current MVP Boundary

The current Builder MVP supports:

- create and update draft definitions
- version definition JSON
- validate definitions
- render preview artifacts
- record preview runs
- show Builder Studio UI for visual metadata editing

The current Builder MVP must not:

- publish generated modules
- write runtime module files
- run generated migrations
- disable installed modules
- hide existing modules
- delete published modules
- uninstall modules
- roll back published modules
- remove runtime capabilities
- drop tables, columns, media, or business data

## Future Safe Deletion And Removal

The lifecycle must eventually support deleting wrong work safely. A wrong draft definition is very different from a published module with tables, routes, policies, permissions, and user data. Future lifecycle operations should use the least destructive operation that solves the problem:

- delete or archive draft work before publish
- disable or hide published/existing modules before uninstall
- remove metadata before publish
- disable capabilities before deleting capability data
- uninstall only after dependency analysis, backup, data retention decision, and rollback design

Destructive removal must be an explicit future safety flow with warnings, approvals, retention rules, and audit logs.
