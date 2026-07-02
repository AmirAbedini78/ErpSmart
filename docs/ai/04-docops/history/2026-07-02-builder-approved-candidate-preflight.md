# Builder Approved Candidate Preflight

Date: 2026-07-02

## Summary

Added a read-only approved candidate preflight checker.

The checker verifies current approved candidate freshness, checksum match, candidate snapshot validity, dry-run/runtime-write flags, and publish-forbidden safety flags. It does not mutate approval state, create audit logs, write runtime files, run migrations, or publish.
