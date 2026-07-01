# Builder Studio Automation Metadata Editor

Status: MVP implementation note
Date: 2026-07-02

## Scope

This is the first Builder Studio Automation Metadata Editor MVP.

It lets admins describe workflow/task/email/notification/approval/webhook intent inside `definition_json.automation`. It does not execute anything.

## Explicit Non-Goals

- No runtime workflow execution.
- No email sending.
- No real task creation.
- No approval runtime.
- No webhook runtime.
- No publish.
- No write-capable module generation.
- No ERP packs or presets.
- No Core, Warehouse, SaaS, license, updater, or installer changes.

## Metadata Location

Automation metadata lives under:

```json
{
  "automation": {
    "enabled": true,
    "workflows": []
  }
}
```

Raw JSON remains available as the debug and recovery view. Save, validate, and preview remain safe Builder Control Plane operations.

## Future Engine Boundary

A future Workflow Engine may consume this metadata after a separate runtime contract exists. That engine must own execution, retries, auditing, permissions, email sending, task creation, approval routing, and webhook safety.

The AI Builder Agent may propose automation definitions, but it must not execute them. The Business Operations Agent is separate and must use future runtime APIs rather than Builder metadata directly.

## UI Direction

The MVP uses simple lists, inputs, selects, and move up/down buttons. It does not add drag/drop dependencies and does not change global RTL behavior.

## Safety

Automation metadata is definition data only. Preview may preserve the metadata, but runtime execution is future work.
