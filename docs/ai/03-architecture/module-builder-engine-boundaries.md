# Module Builder Engine Boundaries

Status: architecture probe
Date: 2026-07-01

## Direction

ERPSMART should stay a modular monolith for the 20-day demo target. Define internal engine boundaries now so they can become extractable services later without changing product direction.

## Builder Engine

Current modular monolith boundary:

- validates definitions
- renders previews
- produces generated manifests
- runs generated verifiers
- coordinates publish
- records rollback manifests

Later service boundary:

- Builder API and worker service
- owns builder definition/version/preview/publish tables

Storage ownership:

- `builder_definitions`
- `builder_definition_versions`
- preview manifests
- publish manifests
- rollback manifests

Queues/events:

- definition submitted
- validation completed
- preview rendered
- publish requested
- publish completed/failed

API direction:

- Control Plane API receives definitions and lifecycle commands.
- CLI remains engineering harness only.

Must not be synchronous:

- preview rendering
- verifier execution
- publish
- migrations

## Workflow Engine

Current modular monolith boundary:

- workflow definitions
- process instances
- task routing
- approvals
- event listeners

Later service boundary:

- workflow runtime service with event subscriptions

Storage ownership:

- workflow definitions
- workflow instance state
- tasks/approvals

Queues/events:

- record created/updated/deleted
- approval requested
- task assigned
- workflow step completed

Must not be synchronous:

- long-running workflow execution
- external webhooks
- notification fan-out

## AI Builder Agent Engine

Current modular monolith boundary:

- converts admin prompts into builder definitions
- reviews previews
- suggests fixes
- produces diffs

Later service boundary:

- AI planning/review service using Builder Control Plane APIs

Storage ownership:

- prompt sessions
- proposed definitions
- review reports
- AI audit logs

Queues/events:

- prompt submitted
- definition proposal ready
- preview review complete

API direction:

- AI never writes runtime source directly.
- AI calls the same backend Builder Control Plane as Builder Studio and embedded Settings customization.

Must not be synchronous:

- LLM generation
- RAG retrieval/index refresh
- preview review

## AI Business Operations Agent Engine

Current modular monolith boundary:

- uses permissioned APIs/tools after modules are built
- creates records
- updates records
- analyzes operational data
- drafts reports

Later service boundary:

- permissioned business-agent service

Storage ownership:

- agent sessions
- tool calls
- business operation audit logs

Queues/events:

- agent action requested
- tool call approved/denied
- report generated

Must not be synchronous:

- heavy analysis
- report generation
- bulk updates

## RAG/Indexing Engine

Current modular monolith boundary:

- indexes code/docs/contracts/definitions/business docs
- separates Builder/code and business indexes

Later service boundary:

- indexing service plus vector store

Storage ownership:

- index manifests
- chunk manifests
- source checksums

Queues/events:

- source changed
- definition published
- docs curated
- index rebuild requested

API direction:

- vector DB is derived and rebuildable.

Must not be synchronous:

- chunking
- embedding
- full reindex

## Reporting/Analytics Engine

Current modular monolith boundary:

- builds read models
- aggregates operational data
- serves dashboards/reports

Later service boundary:

- reporting worker/API service

Storage ownership:

- materialized summaries
- report definitions
- report runs

Queues/events:

- source data changed
- report refresh requested
- scheduled aggregate run

Must not be synchronous:

- large aggregations
- exports
- AI report drafting

## Notification/Email Engine

Current modular monolith boundary:

- email sending
- reminders
- notification queues
- webhooks

Later service boundary:

- notification/email worker service

Storage ownership:

- notification templates
- delivery logs
- webhook subscriptions
- email queue state

Queues/events:

- notification requested
- email queued
- webhook queued
- delivery failed

Must not be synchronous:

- email sending
- webhook fan-out
- reminder delivery
