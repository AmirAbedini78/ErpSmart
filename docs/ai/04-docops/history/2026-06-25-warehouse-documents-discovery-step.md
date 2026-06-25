# 2026-06-25 — Warehouse Documents / Attachments discovery step

## Context
Warehouse Notes integration is now confirmed working. The debugging process produced reusable architecture lessons: concrete inverse relations are required for Core association sync, pivot schemas must be inspected before adding timestamps, and frontend record-tab components require stable resource paths.

## Decision
Do not implement Documents/Attachments by guesswork. Run a local source-code discovery step first because Concord/ErpSmart has both a business Documents module and likely generic media/attachment capability.

## Added

- `patches/probe_warehouse_documents_contract.sh`
- `patches/probe_warehouse_documents_contract.php`
- `docs/ai/03-architecture/resource-documents-attachments-discovery.md`
- `docs/ai/04-docops/checklists/warehouse-documents-discovery.md`
- Updated Notes integration rules for RAG.

## Next action
Run the probe, inspect `storage/app/warehouse-documents-contract-report.md`, then implement one narrow integration path.
