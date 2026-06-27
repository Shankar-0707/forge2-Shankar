# PulseDesk — System Architecture

> Living document. Update when the system's shape changes, not on every commit.

## 1. Overview

PulseDesk is a **multi-tenant customer support helpdesk platform**. Each customer is an **Organization** (the tenant boundary). All data — users, tickets, conversations, SLAs, audit logs — is scoped to an organization and must never leak across tenants.

The product is delivered as a **decoupled SPA**:

- **Backend API** — Laravel 11, serving JSON over HTTP.
- **Frontend SPA** — React 19, bundled with Vite, talking to the API via Sanctum-authenticated requests.

The single most important architectural rule, repeated everywhere:

> **Every database query MUST be scoped by `organization_id` taken from the authenticated user. Never trust `organization_id` from request input.**

---

## 2. Tech Stack

### Backend
| Concern              | Choice                                            |
| -------------------- | ------------------------------------------------- |
| Language             | PHP 8.2+                                          |
| Framework            | Laravel 11 (slim skeleton, `bootstrap/app.php`)   |
| Auth                 | Laravel Sanctum (stateless tokens for SPA)        |
| Database             | MySQL 8.0 (default) / Postgres 15 (optional)      |
| Cache / Queue / Lock | Redis 7                                           |
| Search               | Laravel Scout + Meilisearch (optional module)     |
| Testing              | Pest 2                                            |
| Static analysis      | Larastan / PHPStan (level 6 target)               |
| Formatting           | Laravel Pint (PSR-12 + Laravel preset)            |

### Frontend
| Concern              | Choice                                            |
| -------------------- | ------------------------------------------------- |
| Language             | TypeScript 5                                      |
| UI framework         | React 19                                          |
| Build tool           | Vite 5                                            |
| Data fetching        | TanStack Query (React Query)                      |
| Routing              | React Router 6                                    |
| Forms                | React Hook Form + Zod                             |
| Styling              | Tailwind CSS 3 + `clsx` / `cva`                   |
| Component primitives | Radix UI + shadcn/ui patterns                     |
| HTTP client          | native `fetch` wrapper                            |

### Infrastructure
| Concern        | Choice                                          |
| -------------- | ----------------------------------------------- |
| Containerizing | Docker + docker-compose (local dev)             |
| CI             | GitHub Actions (Pest + Pint + PHPStan + `tsc`)  |
| Hosting        | Horizontal-scaling app servers + managed DB     |
| Secrets        | `.env` files locally; vault provider in prod    |

---

## 3. High-Level Diagram
