# PulseDesk — Submission

PulseDesk is a multi-tenant help desk and ticket management platform built with Laravel 11 and React 19. It enables organizations to manage support tickets, track agent performance, and collaborate through threaded comments — all scoped securely per organization.

---

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

### Developer Experience
- **Pest test suite** covering every API endpoint (150+ tests)
- Request validation via Form Requests
- API Resources for consistent JSON output
- OpenAPI spec auto-generated at `/api/docs`
- Database seeders for demo data

---

## 🛠 Tech Stack

| Layer        | Technology                          |
|--------------|-------------------------------------|
| Backend      | Laravel 11 (PHP 8.3)                |
| Frontend     | React 19 + TypeScript               |
| Auth         | Laravel Sanctum                     |
| Database     | PostgreSQL 16                        |
| Testing      | Pest 2.x                            |
| Build        | Vite 5                              |
| Styling      | Tailwind CSS 4                      |
| State        | Zustand                             |
| Data Fetching| TanStack Query 5                    |

---

## 📋 Demo Instructions

### Prerequisites
- PHP 8.3+
- Composer
- Node.js 20+
- PostgreSQL 16 (or SQLite for quick demo)

### Setup
