# ERPSMART Project Map — Warehouse Template Module Update

## Warehouse Template Module

Warehouse is being used as a canonical module for the future AI/local-RAG Module Builder.

Implemented capabilities now include:

- CRUD and Resource UI
- Permission policy and permission registration
- Boolean field contract
- Custom fields
- Import / Export
- Clone and delete actions
- Notes integration
- Attachments/media integration
- Attachment delete action
- Detail edit route stability

## Current Architecture Focus

The project is moving from single-feature implementation toward a capability matrix that can power a builder. Each feature must be documented as:

- backend contract;
- frontend component contract;
- database/pivot contract;
- route contract;
- verification commands;
- failure modes and fixes.
