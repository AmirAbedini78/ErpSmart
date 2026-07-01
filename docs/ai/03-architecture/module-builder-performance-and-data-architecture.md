# Module Builder Performance And Data Architecture

Status: architecture probe
Date: 2026-07-01

## Principle

Use MySQL/PostgreSQL as the source of truth. Do not use the transactional database as the place for every heavy computation. Use Redis for cache, locks, queues, rate limits, and short-lived state.

The 20-day demo target should use a modular monolith with strong internal boundaries and async jobs, not real microservices.

## Source Evidence

- `config/queue.php` supports `sync`, `database`, `sqs`, and `redis` queues.
- `config/cache.php` supports file, database, Redis, and other cache stores.
- `config/database.php` defines Redis `default` and `cache` connections.
- First-party modules already use queued jobs and queued notifications, including Activities calendar jobs and Mail/Notification classes.

## Queue/Job Strategy

Do not run heavy generation in request lifecycle.

Queue these operations:

- validate definition
- render preview
- run generated verifier
- publish module
- run migrations
- rebuild menu/cache
- rebuild RAG index
- generate reports
- run AI jobs

Web requests should create a command record, transition status, dispatch jobs, and return quickly.

## UI Progress

The UI-first Builder should use status polling or websockets:

- `draft`
- `validating`
- `validated`
- `previewing`
- `previewed`
- `publish_pending`
- `publishing`
- `published`
- `publish_failed`

Draft editing should use optimistic UI and debounced autosave. Expensive validation should be explicit or backgrounded with cancellation/replace semantics.

## Caching

Use Redis for:

- Builder preview locks
- publish locks
- status progress
- rate limits
- short-lived AI job state
- cache invalidation fan-out

Keep durable state in database tables and durable storage. Redis is not the source of truth.

Cache invalidation must cover:

- resource metadata
- menus
- settings menus
- field settings
- custom field cache
- frontend route/config manifests
- RAG/index manifests

## RAG Indexing

RAG indexing should be chunked and batched.

Recommended behavior:

- index only curated docs/contracts/manifests
- store source checksum and schema version
- separate Builder RAG from Business Operations RAG
- rebuild indexes from source artifacts
- do not let vector DB become source of truth

## Reporting And Analytics

Reporting/Analytics should avoid overloading transactional tables.

Use:

- read models
- materialized summaries
- scheduled aggregate jobs
- incremental refresh jobs
- explicit indexes for high-use report filters

## Database Indexing

Builder tables need indexes for:

- status
- module/entity name
- checksum
- owner/user
- tenant/company when SaaS is enabled
- created/updated timestamps

Runtime generated tables should include indexes declared by the definition and capability-driven indexes required by first-party contracts.

## Audit/Event Log

Every control-plane transition should write an event:

- actor
- previous state
- next state
- definition version
- job id
- checksum
- error summary when failed

Publish, rollback, and schema-changing operations require durable audit logs.

## Resource Limits

Jobs need:

- timeouts
- retry policies
- idempotency keys
- publish locks
- preview cleanup
- maximum file count
- maximum definition size
- maximum field count per module until DDL performance is proven

## Horizontal Scale Path

Stay modular-monolith first. The scale path is:

1. move heavy work to queues
2. move queues/cache/locks to Redis
3. move preview artifacts to object storage
4. split derived read models from transactional tables
5. extract engines only after API contracts and storage ownership are stable
