# Resource Detail Capability Matrix

## Purpose

The AI Module Builder must not generate only tables and forms. Existing Concord/ErpSmart resource detail pages expose many optional capabilities. This matrix records them as feature toggles so future modules can be assembled predictably.

## Capability groups

### 1. Base resource capability

| Feature key | Meaning | Backend contract | Frontend contract |
|---|---|---|---|
| `resource.crud` | Create/read/update/delete resource records | Resource + Model + Policy + routes | Index/Create/Edit/View pages |
| `resource.table` | Resource table/list page | `Tableable` | table view/actions |
| `resource.permissions` | Role/permission integration | Policy + registered permissions | UI hides/shows actions |
| `resource.custom_fields` | Admin-defined fields | `AcceptsCustomFields`, `AcceptsUniqueCustomFields` | custom-field forms/table columns |
| `resource.import` | CSV/XLS import | `Importable` | Import UI/routes |
| `resource.export` | Export resource records | `Exportable` | Export UI/actions |
| `resource.clone` | Clone record | `Cloneable` + clone method | clone action |

### 2. Detail page capability

| Feature key | Meaning | Backend contract | Frontend contract |
|---|---|---|---|
| `resource.detail_page` | Record detail screen | resource route + display query | custom or generic detail view |
| `resource.detail.inline_edit` | Edit fields directly from detail | update authorization + field config | Core detail fields / inline edit |
| `resource.detail.notes` | Notes on record | timeline + notes relations + association validation | `RecordTabNote`, `RecordTabNotePanel` |
| `resource.detail.attachments` | Generic file attachments | `Mediable`, `HasMedia`, `Resourceable` contract | `ResourceMediaPanel` |
| `resource.detail.timeline` | Timeline activity stream | `HasTimeline` + Timelineables | `RecordTabTimelinePanel` |
| `resource.detail.changelog` | Audit/changelog stream | changelog traits/listeners | timeline/change components |
| `resource.detail.documents` | Business documents | `HasDocuments`, documentable pivot | document tab/panel |
| `resource.detail.activities` | Tasks/meetings/calls activities | activity associations | activity tab/panel |
| `resource.detail.emails` | Related emails | mail associations | email tab/panel |
| `resource.detail.calls` | Related calls | call associations | call tab/panel |
| `resource.detail.comments` | Comments on resource | comments contracts | comments panel |
| `resource.detail.related_records` | Related resources | `AssociatesResources` | related cards/selectors |

### 3. Automation and enterprise capability

| Feature key | Meaning | Backend contract | Frontend/Builder contract |
|---|---|---|---|
| `resource.workflow_triggers` | Workflow automation trigger support | model/resource trigger classes | Builder exposes trigger events |
| `resource.audit_log` | Business audit trail | changelog/timeline events | detail tab/filter |
| `resource.cards_kpis` | Dashboard cards for resource | Resource cards | dashboard widgets |
| `resource.billable` | Product/billable lines | billable contracts | products panel |
| `resource.email_placeholders` | Mail template placeholders | HasEmail / placeholders | template builder support |

## Warehouse current feature state

```json
{
  "module": "Warehouse",
  "implemented": [
    "resource.crud",
    "resource.table",
    "resource.permissions",
    "resource.custom_fields",
    "resource.import",
    "resource.export",
    "resource.clone",
    "resource.detail_page",
    "resource.detail.notes",
    "resource.detail.attachments"
  ],
  "in_progress_or_next": [
    "resource.detail.timeline",
    "resource.detail.changelog",
    "resource.detail.activities",
    "resource.detail.documents",
    "resource.detail.related_records"
  ]
}
```

## RAG notes

When the local AI searches for how to add a capability to a new module, it should retrieve the capability by feature key, not by UI label only. Example: search for `resource.detail.attachments` to retrieve all required backend and frontend contracts.
