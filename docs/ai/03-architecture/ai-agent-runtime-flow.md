# ERPSMART AI Agent Runtime Flow

Date: 2026-07-02

Status: architecture only. Agent Orchestrator is future work.

## Principle

RAG is for knowing. Tools are for doing.

RAG can help an AI understand ERPSMART architecture, Builder contracts, business data definitions, and safety boundaries. It must not be treated as permission to mutate the system. Any operation that changes state must go through a typed internal tool with validation, permission checks, approvals where required, and audit logging.

## Future Runtime Flow

1. User prompt.
2. Intent detection.
3. RAG/context retrieval.
4. Permission check.
5. Tool selection.
6. Structured input validation.
7. Dry-run/preview if needed.
8. Approval gate if needed.
9. Execution through internal service/tool only.
10. Audit log.
11. Final response with citations/results.

## Safety Rules

Approval and audit are required for risky operations. The AI must not directly write database rows, files, modules, migrations, routes, permissions, or runtime code. It must call approved application services through a Tool Registry action when implemented.

## Current MVP

The current AI-ready area is Builder control-plane first: validate, preview, readiness, dry-run, candidate snapshot, approval status, approved preflight, and publish execution preparation record. Runtime publish remains forbidden.

## Future Work

An Agent Orchestrator may later coordinate RAG, Tool Registry calls, approval gates, audit logs, and final user responses. That orchestrator is not implemented in this batch.
