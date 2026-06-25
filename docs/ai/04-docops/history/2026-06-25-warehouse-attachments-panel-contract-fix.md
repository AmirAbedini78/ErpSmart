# Warehouse Attachments Panel Contract Fix

## Context
Warehouse detail uses a custom Vue view rather than the generic Core record-detail renderer. The first Attachments tab rendered `ResourceMediaPanel` directly with record props, but Core `resource-media-panel` is designed to run as a resource panel and expects panel/record context normally provided by the generic renderer.

## Symptom
Opening the Attachments tab showed a frontend exception:

```text
TypeError: Cannot read properties of undefined (reading 'id')
```

## Fix
- Pass a minimal `attachmentsPanel` object to `ResourceMediaPanel`.
- Guard rendering until `safeResource.id` exists.
- Provide `record`, `resource`, `resourceName`, and `resourceId` context for Core panel components.
- Keep normalized `media` and `media_count` on `safeResource`.

## RAG Rule
When reusing Core panel components inside a custom resource detail view, do not assume the component is prop-only. Check whether the generic resource renderer normally supplies panel metadata and provide/inject context. For Warehouse, `ResourceMediaPanel` requires panel metadata and record context.
