# Builder Publish Readiness Analyzer

Status: implemented MVP
Date: 2026-07-02

## Purpose

The publish readiness analyzer is a read-only preflight step for future Builder publish. It inspects a Builder definition and returns a structured dependency impact report before any real publish workflow exists.

It does not publish, write runtime module files, run migrations, create real modules, delete modules, uninstall modules, or change definition status.

## Preview vs Readiness vs Future Publish

Preview renders disposable files under `storage/app/module-builder-preview` and records preview metadata.

Publish readiness analyzes the definition, planned files, planned tables, routes, permissions, capabilities, relations, conflicts, warnings, blockers, dependency checks, and rollback requirements. It performs zero writes.

Future publish will be a separate safety-critical workflow with locks, manifests, backups, migrations, post-publish smoke, rollback, and audit logs.

## Current Detection

The MVP analyzer detects:

- definition validation result
- planned generated file paths
- planned table/migration needs
- existing database table conflict via `Schema::hasTable`
- existing module directory conflict via `modules/{Module}`
- obvious route name/URI conflict from the current route collection
- preview-safe capabilities
- metadata-only capabilities
- warning-only capabilities
- unclassified capabilities
- relation target and foreign-key metadata gaps
- rollback requirements
- dependency checks required before publish

## Limits

The analyzer cannot yet guarantee:

- complete frontend route conflict detection
- complete menu conflict detection
- complete permission collision detection
- migration reversibility
- file write safety under concurrent publish
- all SaaS/tenant impacts
- generated verifier success
- rollback correctness

Those checks belong to later publish-planning and publish-execution tasks.

## AI/RAG Use

AI Builder Agent may call the analyzer to explain readiness, blockers, warnings, and required follow-up tasks. It must not treat readiness as actual publish and must not execute publish, migrations, runtime writes, uninstall, rollback, or runtime deletion.

## Next Steps

- add deeper menu and permission conflict analysis
- add generated verifier dry-run analysis
- add migration diff analysis without execution
- add publish manifest planning
- add rollback manifest planning
- keep actual publish as a separate safety-critical implementation
