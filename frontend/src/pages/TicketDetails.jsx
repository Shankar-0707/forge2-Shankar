import { useState, useEffect, useCallback } from "react";
import { useParams, useNavigate } from "react-router-dom";
import ActivityLog from "../components/ActivityLog";
import ConversationThread from "../components/ConversationThread";
import ReplyForm from "../components/ReplyForm";

const STATUS_STYLES = {
  open: "bg-blue-100 text-blue-800 ring-blue-600/20",
  pending: "bg-amber-100 text-amber-800 ring-amber-600/20",
  resolved: "bg-emerald-100 text-emerald-800 ring-emerald-600/20",
  closed: "bg-gray-100 text-gray-700 ring-gray-600/20",
};

const PRIORITY_STYLES = {
  low: "bg-gray-100 text-gray-700 ring-gray-500/20",
  medium: "bg-yellow-100 text-yellow-800 ring-yellow-600/20",
  high: "bg-orange-100 text-orange-800 ring-orange-600/20",
  urgent: "bg-red-100 text-red-800 ring-red-600/20",
};

function formatDate(dateString) {
  return new Intl.DateTimeFormat("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
  }).format(new Date(dateString));
}

async function apiFetch(url, options = {}) {
  const res = await fetch(url, {
    ...options,
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      ...(options.headers || {}),
    },
    credentials: "include",
  });
  if (!res.ok) {
    const error = await res.json().catch(() => ({}));
    throw new Error(error.message || `Request failed (${res.status})`);
  }
  return res.json();
}

export default function TicketDetails() {
  const { id } = useParams();
  const navigate = useNavigate();

  const [ticket, setTicket] = useState(null);
  const [comments, setComments] = useState([]);
  const [activities, setActivities] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [activeTab, setActiveTab] = useState("conversation");

  const loadData = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const [ticketRes, commentsRes, activitiesRes] = await Promise.all([
        apiFetch(`/api/tickets/${id}`),
        apiFetch(`/api/tickets/${id}/comments`),
        apiFetch(`/api/tickets/${id}/activities`),
      ]);
      setTicket(ticketRes.data ?? ticketRes);
      setComments(commentsRes.data ?? commentsRes);
      setActivities(activitiesRes.data ?? activitiesRes);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleReply = async (type, body) => {
    const res = await apiFetch(`/api/tickets/${id}/comments`, {
      method: "POST",
      body: JSON.stringify({ type, body }),
    });
    const newComment = res.data ?? res;
    setComments((prev) => [...prev, newComment]);
    // Refresh activities to capture the new comment event
    apiFetch(`/api/tickets/${id}/activities`)
      .then((r) => setActivities(r.data ?? r))
      .catch(() => {});
  };

  const handleStatusChange = async (newStatus) => {
    try {
      const res = await apiFetch(`/api/tickets/${id}`, {
        method: "PATCH",
        body: JSON.stringify({ status: newStatus }),
      });
      setTicket(res.data ?? res);
      const actRes = await apiFetch(`/api/tickets/${id}/activities`);
      setActivities(actRes.data ?? actRes);
    } catch (err) {
      setError(err.message);
    }
  };

  if (loading) {
    return (
      <div className="flex h-full items-center justify-center">
        <div className="flex flex-col items-center gap-3">
          <div className="h-10 w-10 animate-spin rounded-full border-4 border-gray-200 border-t-indigo-600" />
          <p className="text-sm text-gray-500">Loading ticket…</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex h-full items-center justify-center">
        <div className="text-center">
          <p className="text-lg font-semibold text-red-600">{error}</p>
          <div className="mt-4 flex justify-center gap-3">
            <button
              onClick={loadData}
              className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
            >
              Retry
            </button>
            <button
              onClick={() => navigate("/tickets")}
              className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
              Back to Tickets
            </button>
          </div>
        </div>
      </div>
    );
  }

  if (!ticket) return null;

  const requester = ticket.requester ?? {};
  const assignee = ticket.assignee ?? null;

  return (
    <div className="flex h-full flex-col bg-gray-50">
      {/* ── Header ─────────────────────────────────────── */}
      <div className="border-b border-gray-200 bg-white px-6 py-4">
        <div className="flex items-start justify-between gap-4">
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2 text-sm text-gray-500">
              <button
                onClick={() => navigate("/tickets")}
                className="hover:text-gray-700"
              >
                Tickets
              </button>
              <span>/</span>
              <span>#{ticket.id}</span>
            </div>
            <h1 className="mt-1 truncate text-xl font-bold text-gray-900">
              {ticket.subject}
            </h1>
          </div>
          <div className="flex shrink-0 items-center gap-2">
            <span
              className={`inline-flex items-center rounded-md px-2.5 py-1 text-xs font-semibold uppercase ring-1 ring-inset ${
                STATUS_STYLES[ticket.status] ?? STATUS_STYLES.open
              }`}
            >
              {ticket.status}
            </span>
            <span
              className={`inline-flex items-center rounded-md px-2.5 py-1 text-xs font-semibold uppercase ring-1 ring-inset ${
                PRIORITY_STYLES[ticket.priority] ?? PRIORITY_STYLES.medium
              }`}
            >
              {ticket.priority}
            </span>
          </div>
        </div>
      </div>

      {/* ── Body: Two-column layout ────────────────────── */}
      <div className="flex min-h-0 flex-1 overflow-hidden">
        {/* Main content */}
        <div className="flex min-w-0 flex-1 flex-col">
          {/* Tabs */}
          <div className="flex gap-1 border-b border-gray-200 bg-white px-6">
            <button
              onClick={() => setActiveTab("conversation")}
              className={`border-b-2 px-3 py-2.5 text-sm font-medium transition-colors ${
                activeTab === "conversation"
                  ? "border-indigo-600 text-indigo-600"
                  : "border-transparent text-gray-500 hover:text-gray-700"
              }`}
            >
              Conversation
            </button>
            <button
              onClick={() => setActiveTab("activity")}
              className={`border-b-2 px-3 py-2.5 text-sm font-medium transition-colors ${
                activeTab === "activity"
                  ? "border-indigo-600 text-indigo-600"
                  : "border-transparent text-gray-500 hover:text-gray-700"
              }`}
            >
              Activity Log
            </button>
          </div>

          {/* Tab content */}
          <div className="min-h-0 flex-1 overflow-y-auto px-6 py-4">
            {activeTab === "conversation" ? (
              <ConversationThread
                comments={comments}
                ticketDescription={ticket.description}
                ticketAuthor={requester}
                ticketCreatedAt={ticket.created_at}
              />
            ) : (
              <ActivityLog activities={activities} />
            )}
          </div>

          {/* Reply form pinned to bottom */}
          <div className="border-t border-gray-200 bg-white px-6 py-4">
            <ReplyForm onReply={handleReply} />
          </div>
        </div>

        {/* ── Sidebar ─────────────────────────────────── */}
        <aside className="hidden w-80 shrink-0 overflow-y-auto border-l border-gray-200 bg-white lg:block">
          <div className="divide-y divide-gray-100">
            {/* Requester */}
            <div className="p-5">
              <h3 className="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                Requester
              </h3>
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700">
                  {(requester.name ?? "?")
                    .split(" ")
                    .map((n) => n[0])
                    .slice(0, 2)
                    .join("")
                    .toUpperCase()}
                </div>
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium text-gray-900">
                    {requester.name ?? "Unknown"}
                  </p>
                  <p className="truncate text-xs text-gray-500">
                    {requester.email ?? "—"}
                  </p>
                </div>
              </div>
            </div>

            {/* Assignee */}
            <div className="p-5">
              <h3 className="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                Assignee
              </h3>
              {assignee ? (
                <div className="flex items-center gap-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-semibold text-emerald-700">
                    {assignee.name
                      .split(" ")
                      .map((n) => n[0])
                      .slice(0, 2)
                      .join("")
                      .toUpperCase()}
                  </div>
                  <div className="min-w-0">
                    <p className="truncate text-sm font-medium text-gray-900">
                      {assignee.name}
                    </p>
                    <p className="truncate text-xs text-gray-500">
                      {assignee.email}
                    </p>
                  </div>
                </div>
              ) : (
                <p className="text-sm text-gray-400 italic">Unassigned</p>
              )}
            </div>

            {/* Status control */}
            <div className="p-5">
              <h3 className="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                Status
              </h3>
              <select
                value={ticket.status}
                onChange={(e) => handleStatusChange(e.target.value)}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
              >
                <option value="open">Open</option>
                <option value="pending">Pending</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
              </select>
            </div>

            {/* Dates */}
            <div className="p-5">
              <h3 className="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                Timestamps
              </h3>
              <dl className="space-y-2 text-sm">
                <div className="flex justify-between">
                  <dt className="text-gray-500">Created</dt>
                  <dd className="text-gray-900">{formatDate(ticket.created_at)}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-gray-500">Updated</dt>
                  <dd className="text-gray-900">{formatDate(ticket.updated_at)}</dd>
                </div>
              </dl>
            </div>

            {/* Tags */}
            {ticket.tags?.length > 0 && (
              <div className="p-5">
                <h3 className="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                  Tags
                </h3>
                <div className="flex flex-wrap gap-1.5">
                  {ticket.tags.map((tag) => (
                    <span
                      key={tag}
                      className="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700"
                    >
                      {tag}
                    </span>
                  ))}
                </div>
              </div>
            )}
          </div>
        </aside>
      </div>
    </div>
  );
}
