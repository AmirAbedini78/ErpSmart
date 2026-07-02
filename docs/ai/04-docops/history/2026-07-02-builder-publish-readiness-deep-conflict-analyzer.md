# Builder Publish Readiness Deep Conflict Analyzer

Date: 2026-07-02

## Summary

Hardened the publish readiness analyzer with deeper read-only report sections and a diagnostic plan artifact under `storage/app/builder-publish-readiness/{definition_id}/publish-readiness-plan.json`.

## Added Report Sections

- identity checks
- existing app conflicts
- field impact
- relation impact
- form layout impact
- automation impact
- capability warnings
- publish plan artifact metadata

## Safety Boundary

Runtime write flags remain unchanged:

- `writes_performed = 0`
- `runtime_module_effect = none`
- `publish_executed = false`

The diagnostic artifact is derived storage only. It is not a runtime module write, not a publish, not a migration, and not source of truth.
