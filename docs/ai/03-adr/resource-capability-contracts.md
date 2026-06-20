# Resource Capability Contracts

## Purpose
This document records how Core Resource marker contracts change runtime behavior. It exists for the future ErpSmart Module Builder and AI Agent so generated modules do not miss hidden prerequisites.

## Rule
Do not treat a Resource contract as a cosmetic interface. In this architecture, contracts are used by Core controllers, requests, UI metadata, and endpoint authorization to enable or block features.

## Contract map

| Contract | Runtime effect | Required prerequisites |
|---|---|---|
| `WithResourceRoutes` | Core Resource CRUD endpoints are available for the resource. | Resource name resolves through `Innoclapps::resourceByName()`. |
| `Tableable` | `/api/{resource}/table` uses the Resource table class. | `{Entity}Table` class and table method. |
| `Exportable` | `/api/{resource}/export-fields` and `/api/{resource}/export` are valid. | Export-safe fields and export permission behavior. |
| `Importable` | `/api/{resource}/import/*` endpoints are valid. | `import_id` tracking column when imported rows need revert/history. |
| `AcceptsCustomFields` | Resource appears in Custom Field builder selector. | Resource fields must avoid collisions with custom field IDs. |
| `AcceptsUniqueCustomFields` | Custom fields can request unique validation when supported. | Underlying field type must be unique-capable. |
| `Mediable` | Resource media endpoints are valid. | Model/media traits and UX decisions for attachments. |

## Builder generation sequence
When the Builder enables a capability, it must generate the full chain:

```text
capability choice
  -> Resource contract
  -> database prerequisite
  -> model fillable/cast/trait prerequisite
  -> policy/permission prerequisite
  -> frontend affordance
  -> API smoke tests
  -> SmartDocs/RAG update
```

## Warehouse lesson
Warehouse import/export buttons existed in the Resource UI pattern, but backend capability must be explicit. Enabling import also required a schema addition:

```text
warehouses.import_id nullable indexed
```

This prevents a later failure in import/revert flows where Core expects imported rows to be traceable by `import_id`.
