import { useState, useEffect, useMemo, useCallback } from 'react';
import TicketCard from '../components/TicketCard';
import TicketFilters from '../components/TicketFilters';
import { api } from '../lib/api';

const STATUS_COLUMNS = [
  { key: 'open', label: 'Open', accent: 'border-t-blue-500', dot: 'bg-blue-500' },
  { key: 'in_progress', label: 'In Progress', accent: 'border-t-amber-500', dot: 'bg-amber-500' },
  { key: 'pending', label: 'Pending', accent: 'border-t-purple-500', dot: 'bg-purple-500' },
  { key: 'resolved', label: 'Resolved', accent: 'border-t-emerald-500', dot: 'bg-emerald-500' },
  { key: 'closed', label: 'Closed', accent: 'border-t-slate-500', dot: 'bg-slate-500' },
];

const PRIORITY_ORDER = { urgent: 0, high: 1, medium: 2, low: 3 };

function StatCard({ label, value, icon, color }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <div className="flex items-center gap-3">
        <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${color}`}>
          {icon}
        </div>
        <div>
          <p className="text-2xl font-bold leading-none text-slate-900">{value}</p>
          <p className="mt-1 text-xs font-medium text-slate-500">{label}</p>
        </div>
      </div>
    </div>
  );
}

function EmptyState({ message }) {
  return (
    <div className="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 py-16">
      <svg className="mb-3 h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
      <p className="text-sm font-medium text-slate-500">{message}</p>
    </div>
  );
}

function BoardView({ tickets, onTicketClick }) {
  const grouped = useMemo(() => {
    const groups = {};
    STATUS_COLUMNS.forEach((col) => {
      groups[col.key] = [];
    });
    tickets.forEach((ticket) => {
      if (groups[ticket.status]) {
        groups[ticket.status].push(ticket);
      }
    });
    // Sort each column by priority (urgent first), then SLA
    Object.values(groups).forEach((list) => {
      list.sort((a, b) => {
        const prioDiff = (PRIORITY_ORDER[a.priority] ?? 9) - (PRIORITY_ORDER[b.priority] ?? 9);
        if (prioDiff !== 0) return prioDiff;
        if (a.sla_due_at && b.sla_due_at) {
          return new Date(a.sla_due_at) - new Date(b.sla_due_at);
        }
        if (a.sla_due_at) return -1;
        if (b.sla_due_at) return 1;
        return 0;
      });
    });
    return groups;
  }, [tickets]);

  return (
    <div className="flex gap-4 overflow-x-auto pb-4">
      {STATUS_COLUMNS.map((col) => {
        const columnTickets = grouped[col.key] ?? [];
        const breachedCount = columnTickets.filter((t) => t.sla_status === 'breached').length;

        return (
          <div
            key={col.key}
            className={`flex w-72 flex-shrink-0 flex-col rounded-xl border border-t-4 ${col.accent} border-slate-200 bg-slate-50`}
          >
            <div className="flex items-center justify-between px-3 py-2.5">
              <div className="flex items-center gap-2">
                <span className={`h-2 w-2 rounded-full ${col.dot}`} />
                <h3 className="text-sm font-semibold text-slate-700">{col.label}</h3>
                <span className="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-600">
                  {columnTickets.length}
                </span>
              </div>
              {breachedCount > 0 && (
                <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-600">
                  {breakedCount} breached
                </span>
              )}
            </div>

            <div className="flex-1 space-y-2 overflow-y-auto px-2 pb-2" style={{ maxHeight: 'calc(100vh - 340px)' }}>
              {columnTickets.length === 0 ? (
                <div className="rounded-lg border-2 border-dashed border-slate-200 py-6 text-center">
                  <p className="text-xs text-slate-400">No tickets</p>
                </div>
              ) : (
                columnTickets.map((ticket) => (
                  <TicketCard key={ticket.id} ticket={ticket} onClick={onTicketClick} />
                ))
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
}

function ListView({ tickets, onTicketClick }) {
  const sorted = useMemo(() => {
    return [...tickets].sort((a, b) => {
      // Breached first, then at risk, then by priority
      const slaOrder = { breached: 0, at_risk: 1, on_track: 2, none: 3 };
      const slaDiff = (slaOrder[a.sla_status] ?? 9) - (slaOrder[b.sla_status] ?? 9);
      if (slaDiff !== 0) return slaDiff;

      const prioDiff = (PRIORITY_ORDER[a.priority] ?? 9) - (PRIORITY_ORDER[b.priority] ?? 9);
      if (prioDiff !== 0) return prioDiff;

      return new Date(b.created_at) - new Date(a.created_at);
    });
  }, [tickets]);

  return (
    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
      {sorted.map((ticket) => (
        <TicketCard key={ticket.id} ticket={ticket} onClick={onTicketClick} />
      ))}
    </div>
  );
}

export default function Dashboard() {
  const [view, setView] = useState('board');
  const [tickets, setTickets] = useState([]);
  const [agents, setAgents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [filters, setFilters] = useState({
    search: '',
    status: 'all',
    priority: 'all',
    assignee_id: 'all',
  });

  // Fetch agents for filter dropdown
  useEffect(() => {
    api
      .get('/agents')
      .then((res) => setAgents(res.data ?? []))
      .catch(() => {});
  }, []);

  // Fetch tickets with debounce on search
  useEffect(() => {
    setLoading(true);
    setError(null);

    const params = {};
    if (filters.search.trim()) params.search = filters.search.trim();
    if (filters.status !== 'all') params.status = filters.status;
    if (filters.priority !== 'all') params.priority = filters.priority;
    if (filters.assignee_id !== 'all') params.assignee_id = filters.assignee_id;

    const debounce = setTimeout(() => {
      api
        .get('/tickets', params)
        .then((res) => {
          setTickets(res.data ?? []);
        })
        .catch((err) => setError(err.message || 'Failed to load tickets'))
        .finally(() => setLoading(false));
    }, 300);

    return () => clearTimeout(debounce);
  }, [filters]);

  const handleTicketClick = useCallback((ticket) => {
    // Navigate to ticket detail — wire up with router when available
    console.log('Open ticket:', ticket.id);
  }, []);

  const stats = useMemo(() => {
    const active = tickets.filter(
      (t) => t.status !== 'resolved' && t.status !== 'closed'
    );
    return {
      total: tickets.length,
      active: active.length,
      breached: active.filter((t) => t.sla_status === 'breached').length,
      atRisk: active.filter((t) => t.sla_status === 'at_risk').length,
      urgent: active.filter((t) => t.priority === 'urgent').length,
      unassigned: active.filter((t) => !t.assignee).length,
    };
  }, [tickets]);

  return (
    <div className="min-h-screen bg-slate-100">
      {/* Page header */}
      <div className="border-b border-slate-200 bg-white">
        <div className="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-xl font-bold text-slate-900">Tickets Dashboard</h1>
              <p className="mt-0.5 text-sm text-slate-500">
                Monitor and manage all support tickets
              </p>
            </div>
          </div>
        </div>
      </div>

      <div className="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        {/* Stats */}
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <StatCard
            label="Total Tickets"
            value={stats.total}
            color="bg-slate-100 text-slate-600"
            icon={
              <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
            }
          />
          <StatCard
            label="Active"
            value={stats.active}
            color="bg-blue-100 text-blue-600"
            icon={
              <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
            }
          />
          <StatCard
            label="SLA Breached"
            value={stats.breached}
            color="bg-red-100 text-red-600"
            icon={
              <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
            }
          />
          <StatCard
            label="SLA At Risk"
            value={stats.atRisk}
            color="bg-amber-100 text-amber-600"
            icon={
              <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            }
          />
          <StatCard
            label="Urgent"
            value={stats.urgent}
            color="bg-orange-100 text-orange-600"
            icon={
              <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
              </svg>
            }
          />
          <StatCard
            label="Unassigned"
            value={stats.unassigned}
            color="bg-purple-100 text-purple-600"
            icon={
              <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            }
          />
        </div>

        {/* Filters */}
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <TicketFilters
            filters={filters}
            onFilterChange={setFilters}
            agents={agents}
            view={view}
            onViewChange={setView}
          />
        </div>

        {/* Content */}
        {error ? (
          <div className="rounded-xl border border-red-200 bg-red-50 p-4">
            <div className="flex items-center gap-2 text-sm text-red-700">
              <svg className="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              {error}
            </div>
          </div>
        ) : loading ? (
          <div className="flex items-center justify-center py-20">
            <div className="flex flex-col items-center gap-3">
              <svg className="h-8 w-8 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p className="text-sm text-slate-400">Loading tickets...</p>
            </div>
          </div>
        ) : tickets.length === 0 ? (
          <EmptyState message="No tickets match your filters. Try adjusting your search." />
        ) : view === 'board' ? (
          <BoardView tickets={tickets} onTicketClick={handleTicketClick} />
        ) : (
          <ListView tickets={tickets} onTicketClick={handleTicketClick} />
        )}
      </div>
    </div>
  );
}
