# Sprint 03 -- SLA Policies, Queues & Activity Logs

Goal: Build SLA target timers, ticket assignment queues, audit trails, and dashboard metrics endpoints.
Models: Hermes = `deepseek/deepseek-v4-pro`, OpenClaw = `z-ai/glm-5.1`

## Issues
- [x] #3.1 SlaPolicy model, migration, and calculations (time remaining and breach state).
- [x] #3.2 ActivityLog model and event trigger hooks (tracking changes to status/assignee).
- [x] #3.3 Ticket Claim & Reassignment routes with role-based policies.
- [x] #3.4 Dashboard metrics API endpoint (/api/v1/dashboard/metrics).
- [x] #3.5 Feature tests verifying SLA logic, audit logs, and dashboard response payload.

## Outcome
- Shipped: SLA policies, activity log listeners, metrics API, and queue controllers.
- Slipped / moved to next sprint: None.
- PRs: PR #3 (merged by Shankar)
