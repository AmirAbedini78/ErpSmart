# 2026-06-27 - Warehouse Detail Edit Click/Modal Stability

## Problem

The Warehouse detail page showed Edit buttons that did not open the edit form. Logs showed the previous patch accidentally put the edit click handler on the Back button, while the actual Edit buttons had no click handler.

The Back button also rendered a JSON/object translation value instead of a clean label.

## Fix

- Replaced navbar action buttons with native buttons to avoid custom component event forwarding ambiguity.
- Added explicit `goBackToIndex` action.
- Added safe string fallback translations for Back and Edit labels.
- Added explicit `openInlineEditForm` click handler on the Edit action.
- Remounts the edit form with `nextTick`.
- Teleports the edit slideover to body.

## Builder lesson

Detail page actions are base capability contracts and must be tested independently before adding Notes, Attachments, Timeline, Activities, Documents, Calls, Emails, and Changelog.
