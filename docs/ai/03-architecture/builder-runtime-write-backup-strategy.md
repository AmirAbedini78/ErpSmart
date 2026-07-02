# ERPSMART Builder Runtime Write Backup Strategy

Date: 2026-07-02

Status: planning only. Runtime backups for writes are not implemented.

## Purpose

Future runtime writes must be reversible where possible. Before overwriting any runtime file, Builder must create a backup and record hashes in the rollback manifest.

## Backup Location

Future backup root:

`storage/app/builder-publish-backups/{definition_id}/{execution_id}`

## Backup Manifest Fields

Each backup entry must record:

- `original_path`
- `backup_path`
- `pre_write_sha256`
- `post_write_sha256`
- `existed_before`
- `write_action`: `create`, `overwrite`, or `skip`

## Rollback Manifest Integration

The rollback manifest must reference backups before runtime files are changed. Created files should be recorded for future removal if rollback is safe. Overwritten files should be restorable from backups after hash verification.

## Limitations

Backups are not a full rollback strategy for database schema, media, uploaded files, cache, queues, or business data created after publish. Data retention policy and compensating migrations are future work.

## Current Boundary

No runtime write backup implementation exists in the current MVP. This document defines the future requirement only.
