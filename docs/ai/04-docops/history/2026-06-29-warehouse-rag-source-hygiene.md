# Warehouse RAG Source Hygiene

Status: applied
Date: 2026-06-29

Warehouse is now the MVP canonical template candidate, but the module directory contains stale backup files and historical/generated noise. Those files must not become source evidence for RAG, AI Agent, or Module Builder generation.

Added:
- `docs/ai/05-rag/exclusions/warehouse-canonical-template-exclusions.json`
- `patches/verify_warehouse_rag_source_hygiene.php`

Excluded patterns include:
- `*.bak-*`
- `modules/Warehouse/**/*.bak-*`
- `public/build/**`
- `node_modules/**`
- `vendor/**`
- `storage/framework/**`
- `storage/logs/**`
- bulk `storage/app/**`
- compiled/minified assets and cache files
- `docs/ai/04-docops/superseded/**`
- `composer.lock` and `package-lock.json` for semantic generation unless a dependency audit is requested

Allowed canonical Warehouse evidence includes the current Warehouse Resource, model, JSON Resource, provider, policy, Vue detail/index/create/edit views, floating modal, verifier, and relevant docs/ai manifests/history notes.

This protects Module Builder and AI Agent work by forcing generation to use current source contracts and curated docs instead of stale backups, generated build output, dependency code, old failed attempts, or uncurated reports.
