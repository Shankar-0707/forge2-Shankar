# Sprint 01 -- Scaffolding, Auth & Tenancy

Goal: Establish the base Laravel 11 scaffold, database migrations, multi-tenant guard, and Sanctum auth APIs.
Models: Hermes = `deepseek/deepseek-v4-pro`, OpenClaw = `z-ai/glm-5.1`

## Issues
- [x] #1.1 Database migrations for Organizations, Users, and core security schemas.
- [x] #1.2 Tenant-scoped model query filters (automatically scoping requests to organization_id).
- [x] #1.3 Sanctum Auth endpoints (Registration, Login, and Logout).
- [x] #1.4 Integration tests verifying authorization gates and tenant isolation.

## Outcome
- Shipped: Core backend infrastructure, authentication controller, and EnsureSameTenant middleware.
- Slipped / moved to next sprint: None.
- PRs: PR #1 (merged by Shankar)
