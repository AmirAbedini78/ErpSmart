# Warehouse Detail Navigation Contract

The Warehouse module detail page must keep base navigation stable before enabling additional capabilities.

## Rules

- Back action returns to `/${resourceName}`.
- Edit action must not be wired to the back button.
- Detail inline edit can be enabled as a separate capability: `detail_inline_edit`.
- The edit slideover is teleported to `body` to avoid layout and slot clipping issues.
- Translation values rendered in buttons must be strings; fallbacks are required when module translations return a nested object.

## Builder impact

Future Module Builder should generate and test:

- `index_route`
- `view_route`
- `edit_route`
- `detail_back_action`
- `detail_edit_action`
- `detail_inline_edit`
- `safe_translation_fallbacks`
