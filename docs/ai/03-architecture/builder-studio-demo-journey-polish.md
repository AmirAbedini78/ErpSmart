# Builder Studio Demo Journey Polish

Status: demo journey note
Date: 2026-07-02

## Current Demo Journey

Builder Studio now supports a coherent UI-first demo path:

1. Open `/builder/definitions`.
2. Create a draft.
3. Edit module identity.
4. Add fields.
5. Design form layout metadata.
6. Design automation metadata.
7. Toggle capabilities.
8. Add relations if needed.
9. Review raw JSON.
10. Save.
11. Validate.
12. Preview.

Publish is intentionally absent.

## Section Flow

The detail page exposes these sections:

- Demo Flow
- Identity
- Fields
- Form Layout
- Automation
- Capabilities
- Relations
- Raw JSON
- Validate & Preview

The sidebar summary shows module/entity/resource information, field count, relation count, enabled capability count, form layout status, automation status, current status, and safety labels.

## Manual Browser Smoke Flow

1. Navigate to `/builder/definitions`.
2. Confirm the index shows total/draft/validated/previewed counts.
3. Create a draft.
4. Confirm the detail summary shows `Preview-only MVP`, `No publish`, and `No runtime writes`.
5. Edit identity, fields, form layout metadata, automation metadata, capabilities, and relations.
6. Confirm raw JSON changes.
7. Save.
8. Validate.
9. Preview.
10. Confirm preview output is visible.
11. Confirm no publish button exists.
12. Confirm no runtime module is created.

## What Users Should See

- Clear create draft CTA.
- Section navigation.
- Builder summary.
- Safety notices.
- Metadata-only warnings for Form Layout and Automation.
- Save, Validate, and Preview actions.

## Intentionally Absent

- Publish.
- Runtime form renderer.
- Runtime workflow execution.
- Email sending.
- Task creation.
- Approval runtime.
- Webhook runtime.
- Write-capable module generation.
- ERP packs/presets.

## Known Build Warnings

The current frontend build may show non-blocking warnings:

- Sass legacy JS API deprecation.
- Tailwind content configuration warning.
- Runtime font path warnings.
- Large chunk warnings.

These are not blockers while the build completes successfully.

## Future Module Lifecycle Roadmap

Module lifecycle and removal must be handled as a separate safety-critical Builder Module Lifecycle task. Future scope should include:

- disable module
- hide module from UI
- delete draft definition
- archive definition
- uninstall published module
- rollback published module
- remove wrong capabilities safely
- remove existing modules that should not be available in the app

This future work needs safety contracts, dependency checks, data retention rules, rollback rules, and RAG updates before implementation.

## Next Recommended Step

The next safe step is a manual browser smoke with screenshots or a lightweight Playwright probe for the Builder Studio demo journey.
