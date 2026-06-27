# SKILL.md — PulseDesk Competency Ledger

> A living record of skills demonstrated, technologies learned, and competencies applied while building **PulseDesk**, a multi-tenant helpdesk platform built on Laravel 11 + React 19.

---

## 🛠️ Core Technologies

### Backend — Laravel 11
| Skill | Demonstrated By |
|-------|-----------------|
| Laravel 11 skeleton & bootstrap | App service providers, `bootstrap/app.php` middleware configuration |
| Sanctum SPA authentication | Cookie-based auth, CSRF tokens, `config/sanctum.php` |
| Multi-tenancy via organization scoping | Global query scopes, `$org = auth()->user()->organization_id` |
| Eloquent ORM | Models, relationships, factories, observers |
| Form Requests | Typed request classes with authorization + validation rules |
| API Resources | Consistent JSON response shaping |
| Pest PHP testing | Feature + unit tests for every endpoint |
| Policy-based authorization | TicketPolicy, OrganizationPolicy, RolePolicy |
| Database migrations | Schema design, foreign keys, indexes |
| Route caching & middleware groups | `auth:sanctum`, `verified`, rate limiting |

### Frontend — React 19
| Skill | Demonstrated By |
|-------|-----------------|
| React 19 functional components | Hooks-first approach (useState, useEffect, useReducer) |
| Concurrent rendering patterns | `useTransition`, `useDeferredValue` for ticket lists |
| Context API | Auth context, organization context, theme context |
| Custom hooks | `useAuth`, `useTickets`, `useNotification` |
| Lazy loading & code splitting | `React.lazy()` for dashboard panels |
| Fetch layer | AbortController, SWR-style caching pattern |
| Accessibility | ARIA attributes, keyboard navigation, focus management |
| Tailwind CSS v4 | Utility-first styling, design tokens, dark mode |

### Infrastructure & Tooling
| Skill | Tool |
|-------|------|
| Version control | Git with conventional commit messages |
| CI/CD | GitHub Actions — Pest runs on every PR |
| Containerization | Docker Compose for local dev |
| Asset bundling | Vite |
| Code quality | PHPStan / Larastan, PHP CS Fixer, ESLint |
| Database | PostgreSQL 16, Redis for cache/queues |

---

## 📐 Architectural Competencies

### 1. Multi-Tenant Data Isolation
Every database query is scoped by the authenticated user's `organization_id`. We enforce this through:
- **Global query scopes** on Eloquent models (Ticket, User, Category, etc.)
- **Controller-level guard**: `abort_if($model->organization_id !== $org, 403)`
- **Never** accepting `organization_id` from request input — always from `auth()->user()`
