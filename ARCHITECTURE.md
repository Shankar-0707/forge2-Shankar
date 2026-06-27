# Architecture -- PulseDesk

## Multi-tenancy approach
Describe how every record is scoped to an organization_id, and how the tenant is derived
from the AUTHENTICATED user/session (NOT from a client-supplied id). Note your global scope /
middleware / policy approach.

## Data model (fill in)
- Organization (tenant)
- User (belongs_to Organization; role: admin | agent | customer)
- Ticket (subject, description, status, priority, requester_id, assignee_id, org_id, timestamps)
- Comment (ticket_id, author_id, body, is_internal)
- SlaPolicy (org_id, priority, response_minutes, resolution_minutes)   # Should-tier
- ActivityLog (ticket_id, actor_id, action, meta, created_at)          # Should-tier

## API routes (fill in -- routes/api.php)
| Method | Path | Auth | Notes |
| --- | --- | --- | --- |
| POST | /api/register | - | |
| POST | /api/login | - | returns Sanctum token |
| GET  | /api/tickets | agent/admin | tenant-scoped, filterable |
| POST | /api/tickets | any | |
| GET  | /api/tickets/{id} | tenant | |
| PUT  | /api/tickets/{id} | agent/admin | |
| POST | /api/tickets/{id}/comments | tenant | public reply / internal note |

## Key decisions (log them as you go)
- ...
