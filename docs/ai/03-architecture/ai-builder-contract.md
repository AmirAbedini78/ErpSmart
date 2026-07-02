# ERPSMART AI Builder Contract

Date: 2026-07-02

Status: architecture only. AI Builder Agent execution is future work.

## Allowed AI Builder Work

AI may generate Module Definition drafts, suggest fields, relations, capabilities, form layout metadata, and automation metadata. AI may call validation, preview, readiness analysis, dry-run generation, candidate snapshot creation, approval status reads, approved candidate preflight, and publish execution record preparation only through safe Tool Registry actions when implemented.

## Output Requirements

AI output must be JSON-schema-valid before Builder uses it. AI-generated module changes remain drafts or candidates until human review and applicable approval gates complete.

## Forbidden Claims And Actions

AI must not claim a module is built or published unless runtime publish actually exists and has completed. AI must not bypass the definition lifecycle, approval, preflight, lock, rollback manifest, or audit gates.

## Current Boundary

Builder actions remain UI-first and Control Plane-first. The Artisan command is an engineering harness. Future AI actions must call the same backend control plane rather than editing files or database state directly.
