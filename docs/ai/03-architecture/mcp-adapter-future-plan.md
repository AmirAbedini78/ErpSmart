# ERPSMART MCP Adapter Future Plan

Date: 2026-07-02

Status: future adapter only. MCP server is not implemented.

## Direction

MCP is a future adapter, not ERPSMART's current core. The internal Tool Registry should come first. A future ERPSMART MCP server may expose selected safe tools, resources, and prompts by adapting Tool Registry actions.

## Phase 1: Read Only

Potential read-only MCP tools:

- `list_modules`
- `get_module_definition`
- `search_docs`
- `get_builder_definition`
- `run_safe_report`

## Phase 2: Draft Write

Potential draft-write MCP tools:

- `create_module_definition_draft`
- `create_proforma_draft`
- `create_task_draft`

## Forbidden MCP Tools

MCP must not expose raw SQL, shell commands, drop table operations, migration writes, publish execution, module uninstall, or permission changes as direct tools.

## Required Controls

All MCP calls must be permission-aware, approval-aware where required, and audit-logged. MCP must not bypass ERPSMART's Tool Registry, Control Plane, approval gates, or safety contracts.
