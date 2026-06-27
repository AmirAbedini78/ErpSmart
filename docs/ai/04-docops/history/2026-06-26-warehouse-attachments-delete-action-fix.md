# 2026-06-26 - Warehouse Attachments Delete Action Fix

## Context

Warehouse attachments were successfully uploaded and persisted after refresh/navigation. The remaining UI difference from existing modules was that media items did not show the delete/remove X action.

## Root cause hypothesis

The custom Warehouse detail page does not inherit the full Core resource detail context. Earlier stability work defaulted `authorizations.update` to `false`. Core media list actions use record/media authorization metadata to decide whether delete controls should be shown.

## Change

Normalize the Warehouse detail record for Core media components:

- default `authorizations.update` to `true` when backend authorization payload is missing;
- normalize media items with `authorizations.delete` while preserving backend-provided authorizations;
- keep server-side authorization unchanged.

## Builder learning

For `resource.detail.attachments`, the Builder must generate both backend media contracts and frontend authorization/context contracts. Upload success alone does not guarantee a complete attachment feature.
