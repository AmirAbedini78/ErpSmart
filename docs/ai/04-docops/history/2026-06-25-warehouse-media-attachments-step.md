# 2026-06-25 — Warehouse Media / Attachments Integration Step

## Summary

Added a safe implementation step for generic Warehouse file attachments using the Core media pipeline discovered from the project.

## Discovery basis

The contract report showed:

- Core has `Mediable` resource contract.
- Core has `MediaController` for resource media uploads.
- Core model-level media behavior is supplied by `HasMedia`.
- Built-in resources such as Deal, Contact, Company and Activity use `Mediable` + `HasMedia`.
- Deal registers a standard resource media panel with `Panel::make('media', 'resource-media-panel')`.

## Implementation

- Add `HasMedia` to Warehouse model.
- Add `Mediable` to Warehouse resource.
- Add standard `resource-media-panel` to Warehouse resource panels.
- Add Attachments tab to Warehouse detail view using `ResourceMediaPanel`.
- Normalize `media` and `media_count` in the detail view resource object.

## Notes checkpoint preserved

The previous Notes integration produced reusable lessons for the local AI/RAG system:

- `resource.path` is required by record tabs.
- `HasTimeline` is required for timeline/record-tab associations.
- Pivot schemas must be respected; no `withTimestamps()` on `noteables`.
- Inverse pivot relations must be concrete model methods, not dynamic closures, when pivot events/touches are involved.

## Next step after validation

If Warehouse attachments work, move to either:

1. Activities / Timeline integration, or
2. Documents CRM association if Warehouse needs formal business documents.
