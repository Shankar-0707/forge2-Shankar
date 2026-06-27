# 🫀 PulseDesk

> A modern, multi-tenant helpdesk and support platform built with **Laravel 11** and **React 19**.

PulseDesk empowers organizations to manage support tickets, customer interactions, and internal workflows from a single, elegant dashboard. Built on a robust multi-tenant architecture, every piece of data is siloed per-organization — keeping your customer conversations private and secure.

---

## 📑 Table of Contents

1. [Overview](#-overview)
2. [Tech Stack](#-tech-stack)
3. [Features](#-features)
4. [Requirements](#-requirements)
5. [Getting Started](#-getting-started)
   - [1. Clone & Install](#1-clone--install)
   - [2. Environment Setup](#2-environment-setup)
   - [3. Database Setup](#3-database-setup)
   - [4. Frontend Setup](#4-frontend-setup)
6. [Running the Project](#-running-the-project)
7. [Project Structure](#-project-structure)
8. [Usage Guide](#-usage-guide)
9. [API Conventions](#-api-conventions)
10. [Testing](#-testing)
11. [Deployment](#-deployment)
12. [Contributing](#-contributing)
13. [License](#-license)

---

## 🎯 Overview

PulseDesk is a full-stack customer support platform that enables organizations to:

- Track and resolve support tickets
- Manage customer relationships
- Collaborate across internal teams
- Monitor performance with real-time analytics

Every query, ticket, and interaction is **automatically scoped** to the authenticated user's organization. No cross-tenant data leaks — ever.

---

## 🧰 Tech Stack

### Backend
| Technology       | Version |
|------------------|---------|
| PHP              | 8.3+    |
| Laravel          | 11.x    |
| Laravel Sanctum  | 4.x     |
| MySQL / PostgreSQL | 8+ / 14+ |
| Redis            | 7+ (cache, queues) |

### Frontend
| Technology | Version |
|------------|---------|
| React      | 19.x    |
| TypeScript | 5.x     |
| Vite       | 5.x     |
| Tailwind CSS | 3.x   |

### Tooling
| Tool          | Purpose             |
|---------------|---------------------|
| Pest PHP      | Backend testing     |
| Vitest        | Frontend testing    |
| Pint          | PHP formatting      |
| ESLint/Prettier | JS/TS linting     |

---

## ✨ Features

- 🔐 **Multi-tenant by design** — organization-level data isolation enforced at the query layer
- 🎫 **Ticket management** — create, assign, prioritize, and resolve support tickets
- 👥 **Team collaboration** — internal notes, mentions, and shared views
- 📊 **Analytics dashboard** — real-time metrics on response times, satisfaction, and volume
- 📨 **Email integration** — convert inbound emails into tickets automatically
- 🌙 **Dark mode** — because support agents work at 2 AM
- ⚡ **SPA performance** — React 19 concurrent rendering for a snappy UX
- 🔔 **Real-time notifications** — powered by Laravel Echo + WebSockets

---

## ✅ Requirements

Before you begin, ensure you have the following installed:

- **PHP** >= 8.3 with extensions: `pdo`, `mbstring`, `xml`, `bcmath`, `curl`, `zip`
- **Composer** >= 2.7
- **Node.js** >= 20.x (LTS recommended)
- **npm** >= 10.x or **pnpm** >= 9.x
- **MySQL** >= 8.0 **or** **PostgreSQL** >= 14
- **Redis** >= 7 (optional but recommended for queues/cache)

---

## 🚀 Getting Started

### 1. Clone & Install
