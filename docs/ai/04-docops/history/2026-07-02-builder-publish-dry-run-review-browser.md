# Builder Publish Dry-Run Review Browser

Date: 2026-07-02

## Summary

Added read-only dry-run review metadata and a Builder Studio review component.

The dry-run report now includes:

- `review`
- `approval_checklist`
- `safety_checklist`
- `next_allowed_actions`
- `forbidden_actions`
- `artifact_summary`

No approval persistence, publish, copy-to-runtime, migrations, or runtime writes were added.
