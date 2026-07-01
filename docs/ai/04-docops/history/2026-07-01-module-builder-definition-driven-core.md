# Module Builder Definition-Driven Core Repair

Date: 2026-07-01

## Summary

Repaired the incomplete definition-driven Module Builder batch.

This repair completed the missing neutral fixtures, schema relation/capability support, architecture note, history note, and verifier for the raw preview builder.

## Key Rules Preserved

- No ERP packs were added.
- Example definitions are fixtures only.
- Generated fields come only from the definition.
- Generated relations come only from the definition.
- Capabilities attach platform behavior only when enabled.
- Preview mode remains the only write mode.
- Real runtime module writes remain disabled.

## Added Fixtures

- `docs/ai/05-rag/examples/definition-driven-custom-module.json`
- `docs/ai/05-rag/examples/custom-related-module-definition.json`

The related fixture uses the preferred relation contract with `targetModel`.

## Verifier

Added:

```text
patches/verify_module_builder_definition_driven_core.php
```

The verifier checks schema validity, fixture validity, preview generation, definition-driven output, relation output, targetModel usage, forbidden path changes, and absence of real generated modules.
