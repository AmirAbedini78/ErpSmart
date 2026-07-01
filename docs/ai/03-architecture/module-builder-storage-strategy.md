# Module Builder Storage Strategy

Status: architecture probe
Date: 2026-07-01

## Principle

The UI-first Builder Control Plane should store drafts, versions, previews, validation reports, publish manifests, and rollback manifests as first-class data. JSON definitions are versioned, not sole source of truth. After publish, runtime business data should be relational/published, not only JSON.

## Draft Layer

Create a future `builder_definitions` table for editable UI drafts:

- `id`
- `name`
- `module_name`
- `entity_name`
- `schema_version`
- `definition_json`
- `status`
- `checksum`
- `created_by`
- `updated_by`
- `tenant_id` or `company_id` when SaaS isolation is proven
- timestamps

Recommended statuses:

- `draft`
- `validating`
- `validated`
- `previewing`
- `previewed`
- `publishing`
- `published`
- `archived`
- `rolled_back`

Draft definitions are editable and can be autosaved. They are not runtime modules.

## Version And Audit Layer

Create a future `builder_definition_versions` table for immutable snapshots:

- `builder_definition_id`
- `version`
- `definition_json`
- `generated_manifest_json`
- `validation_report_json`
- `rollback_manifest_json`
- `diff_json`
- `checksum`
- `created_by`
- timestamps

Every validate, preview, and publish operation should write a version or event entry. Rollback should use an immutable publish manifest, not infer from mutable current JSON.

## Runtime Layer

Published modules create normal runtime assets:

- module files
- resource classes
- models
- policies
- routes
- frontend route/component files
- migrations
- relational business tables

Runtime business data belongs in relational tables. High-volume operational data should not be stored only as flexible JSON. Current Core custom fields support this direction because values are generally added as real columns on resource tables, while option metadata and multi-option pivots are Core-owned.

## Preview Layer

Preview artifacts can stay under `storage/app/module-builder-preview/{Module}/` or move to object storage later.

Store preview metadata in the database:

- preview path
- definition version
- checksum
- validation result
- generated file manifest
- expiration timestamp

Preview generated files are not source of truth. They are disposable artifacts that can be regenerated from a definition version.

## AI/RAG Layer

RAG indexes are derived and rebuildable. Vector DB is not source of truth.

Separate RAG contexts:

- Builder/code/customization RAG
- Business operations/data RAG
- Support/ticket RAG

The Builder RAG should index curated architecture docs, schema contracts, capability manifests, verifier output, and safe first-party evidence. It should exclude `.bak-*`, build assets, vendor, node_modules, caches, generated preview noise unless explicitly curated, and stale failed attempts.

Definitions should be exportable as stable JSON manifests so an AI Builder Agent can propose changes without owning runtime state.

## Recovery Strategy

Required recovery artifacts:

- exported definition JSON
- immutable definition versions
- generated file checksum manifest
- publish manifest
- migration plan
- rollback manifest
- validation report

Do not make generated modules depend only on one mutable JSON blob. The system must survive deleted preview artifacts, failed publish jobs, and RAG index rebuilds.

## SaaS Considerations

Tenant scoping is visible in parts of the project, but Builder tenant ownership needs deeper SaaS probing. For now, model the Builder tables with nullable `tenant_id` or `company_id` fields and enforce tenant-aware publish only after SaaS module boundaries are confirmed.
