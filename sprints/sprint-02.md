# Sprint 02 -- Tickets CRUD & Conversations API

Goal: Implement Tickets CRUD operations, dynamic status/priority filtering, text search, and threaded comment/internal notes engine.
Models: Hermes = `deepseek/deepseek-v4-pro`, OpenClaw = `z-ai/glm-5.1`

## Issues
- [x] #2.1 Tickets API Controller with status/priority filtering and search.
- [x] #2.2 Threaded Comments/Replies API (supporting both public replies and restricted internal agent notes).
- [x] #2.3 Role-based comment visibility scopes (preventing customers from accessing internal notes).
- [x] #2.4 Database Seeder with 1 Org, 1 Admin, 2 Agents, 2 Customers, and 12 tickets.
- [x] #2.5 Feature tests verifying validation, index filters, and comments visibility rules.

## Outcome
- Shipped: Tickets and Comments endpoints, database seeder, and verification tests.
- Slipped / moved to next sprint: None.
- PRs: PR #2 (merged by Shankar)
