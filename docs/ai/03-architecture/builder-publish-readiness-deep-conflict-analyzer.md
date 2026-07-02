# Builder Publish Readiness Deep Conflict Analyzer

Status: implemented MVP hardening
Date: 2026-07-02

## Purpose

The deep conflict analyzer expands publish readiness from a basic preflight report into a structured read-only impact analysis. It helps ERPSMART identify conflicts, metadata-only areas, dependency risks, rollback needs, and publish planning gaps before any real publish, rollback, disable, or uninstall work is implemented.

## Read-Only Boundaries

The analyzer must not:

- publish
- write runtime module files
- create real modules
- run migrations
- drop tables
- create preview runs
- create Builder versions
- uninstall modules
- modify Core, Warehouse, SaaS, licensing, updater, installer, vendor, node_modules, or public build assets

Runtime write flags remain:

- `writes_performed = 0`
- `runtime_module_effect = none`
- `publish_executed = false`

## Diagnostic Artifact

The analyzer may write one diagnostic artifact under:

`storage/app/builder-publish-readiness/{definition_id}/publish-readiness-plan.json`

This artifact is derived diagnostic storage. It is not a runtime module write, not source of truth, not a generated module, and not a publish manifest. It can be regenerated from the Builder definition.

## Conflict Categories

The deep report covers:

- identity checks
- existing app conflicts
- field impact
- relation impact
- form layout impact
- automation impact
- capability impact
- file plan
- database plan
- route/menu/permission plan
- rollback requirements
- dependency checks

## Metadata-Only Handling

Form layout and automation remain metadata-only in the Builder MVP. The analyzer reports their shape and missing field references, but no runtime renderer, workflow engine, email sending, task creation, approvals, or webhooks are executed.

## Why This Comes Before Publish

Publish, rollback, disable, and uninstall need dependency awareness before they can be safe. The deep analyzer gives Builder Studio and future AI Builder Agents a structured way to identify blockers and warnings while preserving the current no-runtime-write boundary.

## Known Limitations

- Menu conflicts are possible-conflict hints, not a full menu registry diff.
- Permission conflicts are planned-name checks only until permission storage is probed further.
- Migration reversibility is not proven.
- File overwrite safety under concurrency is not proven.
- Generated verifier execution is not part of this analyzer.
- SaaS/tenant impact remains future work.

## Next Steps

- add publish manifest planning
- add migration diff planning without execution
- add menu and permission registry probes
- add generated verifier dry-run analysis
- add rollback manifest planning
- keep actual publish in a separate safety-critical implementation batch
