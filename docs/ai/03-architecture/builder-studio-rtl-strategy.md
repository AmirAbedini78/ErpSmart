# Builder Studio RTL Strategy

Status: UI strategy
Date: 2026-07-01

## Principle

Do not force global RTL in this task.

Global RTL can break table layouts, dashboards, sidebars, drag/drop surfaces, field editors, popovers, and third-party widgets if it is enabled without a full audit.

## Current Findings

The main app shell sets the HTML language but does not set a global `dir` attribute in `modules/Core/resources/views/app.blade.php`.

RTL-specific logic appears in document/PDF text handling, not as a full application-wide RTL layout system.

## Builder Studio Preparation

Builder Studio should prepare for future RTL by:

- using existing Core layout components
- using flex/grid gaps instead of manual spacing where practical
- avoiding unnecessary hard-coded left/right in new CSS
- preferring logical content structure
- keeping Builder Studio-specific layout decisions isolated in Builder files
- avoiding custom table or sidebar primitives

## What This Task Must Not Do

- no global `dir="rtl"`
- no Core layout conversion
- no DataTable/ResourceTable RTL changes
- no dashboard/sidebar direction changes
- no full RTL conversion

## Future RTL Task

Future RTL should be a separate audited task that checks:

- global shell direction
- sidebar/navbar behavior
- table and ResourceTable alignment
- modals/slideover placement
- popovers/dropdowns
- date/time pickers
- charts/dashboards
- content builder/editor surfaces
- generated module forms
- Builder Studio visual builder drag/drop behavior
