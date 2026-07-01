# Module Builder Capability Rendering Contract

Status: preview-only implementation contract
Date: 2026-07-01

## Principle

The raw Module Builder preview renders platform capabilities only when the JSON definition enables them. It must not introduce ERP packs, fixed business modules, or default business fields.

Fields, relations, panels, tabs, actions, table behavior, detail behavior, and frontend preview behavior are definition-driven.

## Preview-Safe Implemented Capabilities

These capabilities may affect generated preview files:

- `tableable`
- `hasDetailView`
- `customFields`
- `uniqueCustomFields`
- `importable`
- `exportable`
- `cloneable`
- `media` / `mediable`
- `notes`
- `comments` / `PipesComments`
- `activities`
- `activityComments`
- `activityAssociations`
- `globalSearch`
- `quickCreate`
- `bulkDelete`
- `floatingModal`

Expected preview effects:

- `media` / `mediable` adds `HasMedia`, `Mediable`, media JsonResource data, and the media detail panel.
- `activities` adds `HasActivities`, the activities tab, and `CreateRelatedActivityAction`.
- `activityAssociations` adds `AssociatesResources`.
- `notes` adds the notes tab.
- `comments`, `notes`, or `activityComments` adds `PipesComments`.
- `importable` adds `Importable` and `import_id`.
- `floatingModal` or `frontend.floatingModal` adds the floating edit action and floating modal frontend file.
- `quickCreate` adds quick-create menu registration.
- `globalSearch` controls `$globallySearchable`.

## Schema-Known Warning-Only Capabilities

These capabilities are known to the schema but must not generate unsafe preview APIs yet:

- `documents`
- `calls`
- `emails`
- `emailSending`
- `tasks`
- `workflow`
- `approvals`
- `notifications`
- `timeline`
- `softDeletes`
- `formLayout`
- `stepperForm`
- `sections`
- `conditionalVisibility`

When enabled, preview output must print:

```text
{capability} requested but is future/unsupported in preview; no unsafe APIs are generated
```

## Frontend Preview

Detail preview views consume `resourceInformation.value.detailPage`.
They render `page.panels` and `page.tabs` dynamically.

Local tab component mappings are included only for enabled tab capabilities:

- activities mappings only when `activities` is true
- notes mappings only when `notes` is true

Unsupported capability UI is not generated.

## Fixtures

Capability fixtures are test inputs only:

- `definition-driven-capabilities-off-module.json`
- `definition-driven-capabilities-on-module.json`

They are not presets and must not become Builder defaults.
