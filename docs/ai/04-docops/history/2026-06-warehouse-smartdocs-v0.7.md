# History — Warehouse SmartDocs v0.7

## Date
2026-06-11

## Change type
SmartDocs structure correction + Warehouse MVP execution path.

## Why this update exists
Previous Warehouse docs were not aligned with the agreed SmartDocs folder structure and were too blueprint-like. This update places the documents inside the actual SmartDocs structure and separates:

1. RAG/domain knowledge for local LLM.
2. Manual execution playbook for the human developer.
3. Warehouse domain status.

## Files added

```text
docs/ai/02-domains/entity-lifecycle.md
docs/ai/02-domains/warehouse.md
docs/ai/04-docops/expansion/manual-build-warehouse-module-step-by-step.md
docs/ai/04-docops/history/2026-06-warehouse-smartdocs-v0.7.md
```

## Required INDEX.yml update
Add the entries from `INDEX.warehouse.patch.yml` to:

```text
docs/ai/02-domains/INDEX.yml
```

## Important project discoveries used
- Mature entities are represented through module + provider + resource + model + frontend route registration.
- Deals is the reference mature entity.
- Warehouse should start as MVP, not as full inventory engine.
- Builder compatibility depends on Resource/Core Field/Table integration, not raw CRUD.
- Small LLMs must read lifecycle docs before touching code.
