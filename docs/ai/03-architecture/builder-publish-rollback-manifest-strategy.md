# ERPSMART Builder Publish Rollback Manifest Strategy

Date: 2026-07-02

Status: planning only. Rollback execution is not implemented by this document.

## Purpose

A future Builder publish must create a rollback manifest before any runtime writes. The manifest is the structured record that explains what publish intends to change, what existed before publish, what can be restored, and what cannot be safely reversed automatically.

## Required Timing

The rollback manifest must be created before runtime files are written, before generated migrations are run, and before routes, menus, or permissions are registered. If the manifest cannot be created, publish must stop before changing the runtime application.

## Required Manifest Content

The rollback manifest must record:

- `publish_id`
- `candidate_id`
- `definition_checksum`
- `files_to_create`
- `files_to_modify`
- `files_to_delete_if_rollback`
- `pre_publish_file_hashes`
- `pre_publish_file_backups`
- `migration_plan`
- `migration_status`
- `created_tables`
- `modified_tables`
- route/menu/permission changes
- cache keys affected
- search/vector/RAG indexes affected
- smoke test results
- audit log IDs

## File Rollback

For created files, rollback can usually remove the generated file if no later user changes depend on it. For modified files, rollback must restore a backup and verify the pre-publish hash. For deleted files, rollback must restore the backup and preserve audit evidence.

## Database Rollback

Database rollback is more complicated than file rollback. It may require explicit down migrations, compensating migrations, or data-retention decisions. Data created after publish cannot always be safely removed. Media, uploaded files, and business records require retention policy before any destructive operation.

## Routes, Menus, Permissions, And Cache

Rollback must know which route registrations, menu entries, permissions, policies, cache keys, search indexes, vector indexes, and RAG manifests were affected. Cache and index rebuilds may be safer than trying to restore individual cache keys.

## Automation And Capabilities

Capability-specific rollback must respect data retention. Removing notes, activities, media, custom fields, workflow metadata, or import/export integration may leave dependent data behind. Future rollback should prefer disable/hide first when data dependencies exist.

## Limits

Rollback must not be assumed to be automatic. Some failures may allow automatic restoration before users see the module; other failures require a human-reviewed compensating operation. The manifest must clearly mark irreversible or partially reversible steps.

## Current MVP Boundary

The Builder currently has no publish execution and no rollback execution. This document defines the future manifest and safety expectations only.
