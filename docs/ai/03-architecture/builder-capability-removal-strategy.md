# Builder Capability Removal Strategy

Status: planning only
Date: 2026-07-02

## Purpose

Capabilities are reusable platform features, not fixed business module assumptions. Removing a capability differs by capability type and by whether the definition has been published. This document is planning only; no capability removal is implemented.

## Before Publish

Before publish, metadata-only capabilities can usually be removed from `definition_json` safely:

- form layout metadata
- automation metadata
- warning-only future capabilities
- draft notes about documents, calls, emails, workflows, approvals, notifications, and tasks

The Builder should still validate the resulting definition, update version history, and regenerate preview artifacts so stale preview output is not treated as source truth.

## After Publish

After publish, capability removal requires dependency and data checks:

- notes/comments may have user-entered collaboration data
- activities may have associations, comments, due dates, and reminders
- media may have files and storage references
- custom fields may have table columns, options, and values
- import/export affects actions, permissions, and operational workflows
- global search affects indexes and search actions
- automation/form layout metadata may have UI contracts or future runtime consumers

Runtime capability removal should prefer disable or hide first, then delete later only with explicit confirmation.

## Capability Groups

Data/UI capabilities such as table, detail, custom fields, clone, import, export, quick create, and bulk delete mostly affect generated classes, routes, actions, permissions, and UI controls.

Collaboration/content capabilities such as notes, comments, activities, and activity associations can create user-owned records that should be retained or migrated.

Media/files capabilities affect storage paths, media records, attachment panels, and cleanup policies.

Form layout capabilities are currently metadata-only, but future renderers may bind them to create/update/detail forms.

Automation/process capabilities are currently metadata-only. Future workflow execution may create tasks, emails, notifications, approvals, jobs, logs, and webhook attempts.

Search/import/export capabilities affect operational workflows and indexes. Removing them should not remove historical imported data.

## Recommended Rule

The default first action should be disable, hide, or archive instead of delete. Destructive removal is future work and must require dependency analysis, backup, retention rules, rollback rules, and audit logs.
