import { useState, useEffect, memo } from 'react';

const PRIORITY_CONFIG = {
  urgent: {
    label: 'Urgent',
    badge: 'bg-red-100 text-red-700 border-red-200',
    dot: 'bg-red-500',
    border: 'border-l-red-500',
  },
  high: {
    label: 'High',
    badge: 'bg-orange-100 text-orange-700 border-orange-200',
    dot: 'bg-orange-500',
    border: 'border-l-orange-500',
  },
  medium: {
    label: 'Medium',
    badge: 'bg-yellow-100 text-yellow-700 border-yellow-200',
    dot: 'bg-yellow-500',
    border: 'border-l-yellow-500',
  },
  low: {
    label: 'Low',
    badge: 'bg-slate-100 text-slate-600 border-slate-200',
    dot: 'bg-slate-400',
    border: 'border-l-slate-400',
  },
};

const SLA_CONFIG = {
  breached: {
    container: 'bg-red-50 border-red-200',
    text: 'text-red-700',
    icon: 'text-red-500',
    pulse: true,
  },
  at_risk: {
    container: 'bg-amber-50 border-amber-200',
    text: 'text-amber-700',
    icon: 'text-amber-500',
    pulse: false,
  },
  on_track: {
    container: 'bg-emerald-50 border-emerald-200',
    text: 'text-emerald-700',
    icon: 'text-emerald-500',
    pulse: false,
  },
  none: null,
};

function formatSlaLabel(status, remainingMinutes) {
  if (status === 'none') return null;

  if (status === 'breached') {
    const hours = Math.floor(remainingMinutes / 60);
    const mins = remainingMinutes % 60;
    if (hours >= 24) {
      const days = Math.floor(hours / 24);
      return `Breached ${days}d ${hours % 24}h ago`;
    }
    return `Breached ${hours}h ${mins}m ago`;
  }

  const hours = Math.floor(remainingMinutes / 60);
  const mins = remainingMinutes % 60;

  if (hours >= 24) {
    const days = Math.floor(hours / 24);
    return `${days}d ${hours % 24}h left`;
  }
  if (hours > 0) {
    return `${hours}h ${mins}m left`;
  }
  return `${mins}m left`;
}

function getInitials(name) {
  if (!name) return '?';
  return name
    .split(' ')
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();
}

function Avatar({ user }) {
  if (user?.avatar_url) {
    return (
      <img
        src={user.avatar_url}
        alt={user.name}
        className="h-7 w-7 rounded-full object-cover ring-2 ring-white"
      />
    );
  }

  const initials = getInitials(user?.name);

  return (
    <div className="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 ring-2 ring-white">
      {initials}
    </div>
  );
}

function SlaBadge({ ticket }) {
  const [, forceUpdate] = useState({});
  const [now, setNow] = useState(Date.now());

  useEffect(() => {
    if (ticket.sla_status === 'none' || ticket.sla_status === 'on_track') return;
    const interval = setInterval(() => setNow(Date.now()), 30_000);
    return () => clearInterval(interval);
  }, [ticket.sla_status]);

  useEffect(() => {
    forceUpdate({});
  }, [now]);

  const config = SLA_CONFIG[ticket.sla_status];
  if (!config) return null;

  const label = formatSlaLabel(ticket.sla_status, ticket.sla_remaining_minutes);

  return (
    <div
      className={`inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium ${config.container} ${config.text} ${
        config.pulse ? 'animate-pulse' : ''
      }`}
      title={`SLA due: ${ticket.sla_due_at ? new Date(ticket.sla_due_at).toLocaleString() : 'N/A'}`}
    >
      <svg
        className={`h-3 w-3 ${config.icon}`}
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        strokeWidth={2.5}
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
        />
      </svg>
      {label}
    </div>
  );
}

function TicketCard({ ticket, onClick, compact = false }) {
  const priorityConfig = PRIORITY_CONFIG[ticket.priority] ?? PRIORITY_CONFIG.medium;

  const handleClick = () => {
    onClick?.(ticket);
  };

  return (
    <div
      onClick={handleClick}
      className={`group cursor-pointer rounded-lg border border-l-4 ${priorityConfig.border} border-t-slate-200 border-r-slate-200 border-b-slate-200 bg-white p-3 shadow-sm transition-all hover:shadow-md hover:border-t-slate-300 hover:border-r-slate-300 hover:border-b-slate-300`}
    >
      {/* Top row: ticket number + priority */}
      <div className="mb-2 flex items-center justify-between gap-2">
        <span className="font-mono text-xs text-slate-400 group-hover:text-slate-500">
          {ticket.ticket_number}
        </span>
        <span
          className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium ${priorityConfig.badge}`}
        >
          <span className={`h-1.5 w-1.5 rounded-full ${priorityConfig.dot}`} />
          {priorityConfig.label}
        </span>
      </div>

      {/* Title */}
      <h4 className="mb-2 line-clamp-2 text-sm font-medium leading-snug text-slate-800 group-hover:text-slate-900">
        {ticket.title}
      </h4>

      {/* SLA badge */}
      <div className="mb-2.5">
        <SlaBadge ticket={ticket} />
      </div>

      {/* Bottom row: assignee + meta */}
      {!compact && (
        <div className="flex items-center justify-between gap-2 border-t border-slate-100 pt-2">
          <div className="flex items-center gap-1.5">
            {ticket.assignee ? (
              <>
                <Avatar user={ticket.assignee} />
                <span className="text-xs text-slate-500">{ticket.assignee.name}</span>
              </>
            ) : (
              <div className="flex items-center gap-1.5">
                <div className="flex h-7 w-7 items-center justify-center rounded-full border-2 border-dashed border-slate-300 text-xs text-slate-400">
                  ?
                </div>
                <span className="text-xs text-slate-400">Unassigned</span>
              </div>
            )}
          </div>

          <span className="text-xs text-slate-400">
            {ticket.requester?.name ?? 'Unknown'}
          </span>
        </div>
      )}
    </div>
  );
}

export default memo(TicketCard);
