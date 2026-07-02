# ERPSMART AI Tool Registry Strategy

Date: 2026-07-02

Status: architecture only. Internal Tool Registry is not implemented yet.

## Core Direction

ERPSMART should use an internal Tool Registry as the core abstraction for AI actions. MCP is a future adapter over selected Tool Registry actions, not the core dependency.

## Tool Metadata

Every tool must define:

- `name`
- `purpose`
- `input_schema`
- `output_schema`
- `permission_required`
- `safety_class`
- `approval_required`
- `audit_required`
- write category: `read_only`, `draft_write`, `control_plane_write`, `runtime_write`, or `dangerous_forbidden`

## Initial Tool Categories

- builder_read_tools
- builder_control_plane_tools
- business_read_tools
- business_draft_write_tools
- workflow_tools
- reporting_tools

## Forbidden Dangerous Tools

The Tool Registry must not expose raw SQL, shell commands, direct migration writes, drop table actions, direct permission mutation, runtime publish execution, or module uninstall as agent-callable tools.

## Builder Tool Readiness

Safe Builder tools can map to existing backend endpoints and services, such as validation, preview, approved candidate preflight, and publish execution record preparation. The publish execution record action remains control-plane-only and reports `runtime_writes_performed = 0`.
