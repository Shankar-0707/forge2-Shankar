# Submission checklist -- Forge 2 / Edition 1 (PulseDesk)

Tick each and point to the in-repo path. Everything must be committed in THIS repo.

- [x] Repo is public, named forge2-Shankar
- [x] README has exact run steps; `php artisan migrate --seed` works from a fresh clone
- [x] Backend = Laravel 11 + MySQL ; Frontend = React 19 + Vite + Tailwind
- [x] Multi-tenancy: Org A cannot see Org B data (tenant derived from auth session)
- [x] Hermes config committed -> agents/hermes/hermes-config.yaml (secrets redacted)
- [x] OpenClaw config committed -> agents/openclaw/openclaw.json (secrets redacted)
- [x] agent-log.md shows the real human->Hermes->OpenClaw loop
- [x] sprints/ has >= 2 sprint docs
- [x] Slack proof in slack-export/ (export) or slack-export/screenshots/ (per channel)
- [x] App / agents-running / CI screenshots in evidence/screenshots/
- [x] .github/workflows/ci.yml present + a green run on the Actions tab
- [x] PRs merged by ME (human); commit authors are the agents
- [x] All model calls went through EastRouter
- [x] Models used: deepseek/deepseek-v4-pro (planning), z-ai/glm-5.1 (coding)     Sprints run: 4

---

# PulseDesk — Project Overview

PulseDesk is a multi-tenant help desk and ticket management platform built with Laravel 11 and React 19. It enables organizations to manage support tickets, track agent performance, and collaborate through threaded comments — all scoped securely per organization.

## 🚀 Features Delivered

### Core Platform
- **Multi-tenant architecture** — every query is scoped by `organization_id` from the authenticated user, never from request input
- **Sanctum-based authentication** — cookie + bearer token support for SPA and API consumers
- **Role-based access** — Admin and Agent roles per organization with policy-gated endpoints

### Ticket Management
- Create, read, update, and delete support tickets
- Priority levels (low, medium, high, urgent)
- Status workflow (open → in_progress → resolved → closed)
- Automatic SLA timestamping on resolution
- Full-text search on title and description
- Pagination via Laravel cursor paginator

### Agent Workspace
- Ticket assignment to organization members
- Personal queue view ("My Tickets")
- Inline status updates without page reload
- Real-time comment threads per ticket
- @mention notifications (in-app)

### Dashboard & Analytics
- Organization-level metrics: open vs. resolved, avg. resolution time
- Agent leaderboard (tickets resolved, avg. SLA)
- 7-day trend chart for ticket volume
- Priority distribution breakdown

### Audit & Compliance
- Immutable audit log per ticket (status changes, assignments, edits)
- Organization-scoped — no cross-tenant data leakage
- Soft deletes on tickets with restore capability

## 🛠 Tech Stack

| Layer        | Technology                          |
|--------------|-------------------------------------|
| Backend      | Laravel 11 (PHP 8.4)                |
| Frontend     | React 19 + TypeScript               |
| Auth         | Laravel Sanctum                     |
| Database     | MySQL 8                             |
| Testing      | Pest 2.x                            |
| Build        | Vite 5                              |
| Styling      | Tailwind CSS 4                      |
