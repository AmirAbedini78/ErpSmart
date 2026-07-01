# Current UI Theme System Probe

Status: UI probe
Date: 2026-07-01

## Summary

ERPSMART frontend follows the ConcordCRM/Innoclapps modular Vue pattern. The root `resources/js/app.js` imports each module `resources/js/app.js`; modules register routes and components through `Innoclapps.booting`.

Builder Studio should reuse this module boot pattern and Core UI components. It should not introduce a separate frontend framework, a new layout system, or broad Core theme changes.

## Source Evidence

- `resources/js/app.js`
- `vite.config.js`
- `modules/Core/resources/views/app.blade.php`
- `modules/Core/resources/js/router/index.js`
- `modules/Core/resources/js/router/routes.js`
- `modules/Core/resources/js/components.js`
- `modules/Core/resources/js/views/Settings/Settings.vue`
- `modules/Core/resources/js/views/Settings/SettingsMenu.vue`
- `modules/Warehouse/resources/js/app.js`
- `modules/Warehouse/resources/js/routes.js`
- `modules/Brands/resources/js/app.js`
- `modules/Billable/resources/js/app.js`
- `modules/Translator/resources/js/app.js`

## Frontend Module Boot Pattern

`vite.config.js` maps imports like `@/Warehouse/app.js` to `modules/Warehouse/resources/js/app.js`.

The root app imports first-party module app files. Module app files call:

`Innoclapps.booting((app, router) => { ... })`

Inside the callback, modules register routes with `router.addRoute(...)`, register global components, or both.

## Route Registration Pattern

Standalone module routes are commonly exported from `routes.js` and added with:

`routes.forEach(route => router.addRoute(route))`

Settings routes are added as children of the named `settings` route:

`router.addRoute('settings', { path: 'products', ... })`

Some modules also add absolute settings routes like `/settings/brands`. Builder Studio should prefer normal app routes for advanced work and a settings child route as a quick entrypoint.

## Layout And Page Shell

Main application HTML is in `modules/Core/resources/views/app.blade.php`. It renders:

- sidebar
- navbar
- `router-view`
- floating resource modal
- notifications

Page-level Vue views normally use `<MainLayout>`, with optional `#actions` slot using `NavbarSeparator` and `NavbarItems`.

## Component System

Core globally registers UI plugins and components from `modules/Core/resources/js/components.js`.

Reusable components include:

- `ICard`, `ICardHeader`, `ICardBody`, `ICardFooter`, `ICardHeading`
- `IButton`
- `IFormInput`, `IFormTextarea`, `IFormGroup`, `IFormError`
- `ITable`, `ITableHead`, `ITableBody`, `ITableRow`, `ITableHeader`, `ITableCell`
- `IBadge`
- `IAlert`
- `IOverlay`
- `IText`, `ITextDisplay`
- `ITabGroup`, `ITabList`, `ITabPanels`

Builder Studio should use these rather than custom primitive components.

## Table/List Pattern

Resource modules use `ResourceTable` when the entity is a first-party Resource. Non-resource admin screens use `ITable` directly, as seen in settings/translator and pipeline-style configuration screens.

Builder definitions are Control Plane records, not generated business resources, so the MVP shell uses `ITable` directly rather than `ResourceTable`.

## Settings/Admin Menu Pattern

The settings page uses `SettingsMenu.vue`, driven by `scriptConfig('menu.settings')`. Backend providers add entries through `SettingsMenu`/`SettingsMenuItem`.

For this shell, adding a route under `/settings/software-customization` is safe and narrow. Adding a visible settings menu item should be a later backend/provider task unless a small Builder provider is introduced.

## Translation/I18n Pattern

The app uses Vue I18n from `modules/Core/resources/js/i18n.js`. Backend-generated `lang` data is loaded in `app.blade.php`, and modules usually use `translate('namespace::file.key')` for route titles plus `$t(...)` in templates.

This shell uses stable literal Builder labels because no Builder backend module provider/lang namespace exists yet. A future Builder module should add translation files and route titles through the same i18n pattern.

## Detail Pages, Panels, And Tabs

First-party resource detail pages consume backend-driven `resourceInformation.value.detailPage`, render `Panels`, and render dynamic tabs with `ITabGroup`. Warehouse now follows this StandardDetailPage pattern.

Builder Definition detail is not a generated Resource detail page yet. It is a Control Plane editor shell, so it should use Core cards/forms and avoid pretending to be a resource detail page.

## RTL/Direction Findings

`app.blade.php` sets `<html lang="...">` but no global `dir` attribute was found in the main app shell. RTL-related code appears in document/PDF helpers, not as a global application layout mode.

Builder Studio should prepare for RTL with logical layout choices, but should not force global RTL in this task.

## Reuse For Builder Studio

Reuse:

- module app boot pattern
- route registration through `Innoclapps.booting`
- `MainLayout`
- `NavbarItems`
- Core `I*` UI components
- `ITable` for Control Plane lists
- `IFormTextarea` for temporary raw JSON editing
- `Innoclapps.request()` for API calls

Avoid:

- editing Core theme primitives
- adding a separate router/layout system
- hard-coded global RTL
- custom table widgets
- publishing/generated module UI in this shell
- coupling Builder UI to Warehouse or any ERP preset
