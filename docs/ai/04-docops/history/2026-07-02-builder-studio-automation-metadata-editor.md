# Builder Studio Automation Metadata Editor

Date: 2026-07-02

## Summary

Added the first Builder Studio Automation Metadata Editor MVP.

## UI

- Added `BuilderAutomationEditor.vue`.
- Integrated the editor into `BuilderDefinitionView.vue`.
- Added automation normalization so metadata stays synchronized with raw JSON.
- Added automation capability helper text explaining that runtime execution is future work.

## Metadata

The editor writes draft metadata under `definition_json.automation`:

- `enabled`
- `workflows`
- workflow `trigger`
- workflow `conditions`
- workflow `actions`
- action `config`

## RAG

- Added `builder-automation-metadata-contract.json`.
- Updated Builder Studio component map.
- Updated Builder Studio AI/RAG manifest.
- Updated capability status map for metadata-editable, runtime-warning-only automation capabilities.
- Updated Builder Agent safety boundaries to forbid execution, email sending, task creation, approvals, and webhook calls from Builder metadata.

## Safety

- Publish remains absent.
- No runtime workflow execution was implemented.
- No email sending, task creation, approval runtime, or webhook execution was implemented.
- No backend services/controllers, Core, Warehouse, SaaS, updater, installer, migrations, package, composer, vendor, node_modules, or public build files were changed in this batch.
