import { useState, useEffect, useCallback } from "react";

const API_BASE = import.meta.env.VITE_API_URL || "/api";

/**
 * StatsWidget — metrics summary for the org dashboard.
 *
 * Displays:
 *   • SLA breach rate (% of tickets that missed response target)
 *   • Average first-response time
 *   • Open ticket count
 *
 * Auto-refreshes every 60 seconds. Manual refresh button included.
 *
 * Props:
 *   refreshInterval — auto-refresh interval in ms (default 60000)
 *   compact         — if true, renders a condensed layout
 */
export default function StatsWidget({
    refreshInterval = 60000,
    compact = false,
}) {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const fetchStats = useCallback(async () => {
        try {
            setError(null);
            const res = await fetch(`${API_BASE}/stats/metrics`, {
                credentials: "include",
                headers: { Accept: "application/json" },
            });

            if (!res.ok) throw new Error("Failed to load metrics");

            const data = await res.json();
            setStats(data);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchStats();
        const interval = setInterval(fetchStats, refreshInterval);
        return () => clearInterval(interval);
    }, [fetchStats, refreshInterval]);

    const handleManualRefresh = () => {
        setLoading(true);
        fetchStats();
    };

    if (loading && !stats) {
        return <StatsWidgetSkeleton compact={compact} />;
    }

    if (error && !stats) {
        return (
            <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-center">
                <p className="text-sm font-medium text-red-700">
                    Unable to load metrics
                </p>
                <p className="mt-1 text-xs text-red-500">{error}</p>
                <button
                    onClick={handleManualRefresh}
                    className="mt-3 rounded-md border border-red-300 bg-white px-3 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50"
                >
                    Try again
                </button>
            </div>
        );
    }

    if (!stats) return null;

    const breachRate = stats.sla_breach_rate ?? 0;
    const breachSeverity =
        breachRate >= 25
            ? "critical"
            : breachRate >= 10
            ? "warning"
            : "healthy";

    return (
        <div className={compact ? "space-y-3" : "space-y-4"}>
            {/* Header */}
            <div className="flex items-center justify-between">
                <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-500">
                    Metrics Overview
                </h2>
                <button
                    onClick={handleManualRefresh}
                    disabled={loading}
                    className="text-gray-400 transition hover:text-gray-600 disabled:opacity-50"
                    title="Refresh metrics"
                    aria-label="Refresh metrics"
                >
                    <RefreshIcon spinning={loading} />
                </button>
            </div>

            {/* Metric cards */}
            <div
                className={`grid gap-3 ${
                    compact ? "grid-cols-1 sm:grid-cols-3" : "grid-cols-1 sm:grid-cols-3"
                }`}
            >
                {/* SLA Breach Rate */}
                <MetricCard
                    label="SLA Breach Rate"
                    value={`${breachRate}%`}
                    sublabel={`${stats.sla_breach_count ?? 0} of ${stats.total_tickets ?? 0} tickets`}
                    severity={breachSeverity}
                    icon={<GaugeIcon />}
                    tooltip={`Threshold: ${stats.sla_threshold_minutes ?? "—"} min first response`}
                />

                {/* Avg First Response Time */}
                <MetricCard
                    label="Avg First Response"
                    value={formatResponseTime(stats.avg_first_response_minutes)}
                    sublabel={
                        stats.avg_first_response_minutes === null
                            ? "No responses yet"
                            : "across all tickets"
                    }
                    severity="neutral"
                    icon={<ClockIcon />}
                />

                {/* Open Tickets */}
                <MetricCard
                    label="Open Tickets"
                    value={String(stats.open_ticket_count ?? 0)}
                    sublabel={`${stats.tickets_last_7_days ?? 0} new in 7 days`}
                    severity={
                        (stats.open_ticket_count ?? 0) > 50
                            ? "warning"
                            : "neutral"
                    }
                    icon={<InboxIcon />}
                />
            </div>

            {/* Expanded breakdown (non-compact only) */}
            {!compact && stats.status_breakdown && (
                <StatusBreakdown breakdown={stats.status_breakdown} />
            )}

            {!compact && stats.priority_breakdown && (
                <PriorityBreakdown breakdown={stats.priority_breakdown} />
            )}

            {error && (
                <p className="text-xs text-amber-600">
                    Showing cached data — refresh failed: {error}
                </p>
            )}
        </div>
    );
}

/* --- Sub-components --- */

function MetricCard({ label, value, sublabel, severity = "neutral", icon, tooltip }) {
    const severityStyles = {
        critical: {
            ring: "ring-red-200",
            bg: "bg-red-50",
            text: "text-red-700",
            iconBg: "bg-red-100",
            iconText: "text-red-600",
        },
        warning: {
            ring: "ring-amber-200",
            bg: "bg-amber-50",
            text: "text-amber-700",
            iconBg: "bg-amber-100",
            iconText: "text-amber-600",
        },
        healthy: {
            ring: "ring-green-200",
            bg: "bg-green-50",
            text: "text-green-700",
            iconBg: "bg-green-100",
            iconText: "text-green-600",
        },
        neutral: {
            ring: "ring-gray-200",
            bg: "bg-white",
            text: "text-gray-900",
            iconBg: "bg-indigo-100",
            iconText: "text-indigo-600",
        },
    };

    const s = severityStyles[severity] ?? severityStyles.neutral;

    return (
        <div
            className={`relative rounded-xl border border-gray-200 ${s.bg} p-4 ring-1 ${s.ring} transition hover:shadow-sm`}
            title={tooltip}
        >
            <div className="flex items-start justify-between">
                <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-gray-500">
                        {label}
                    </p>
                    <p className={`mt-1 text-2xl font-bold ${s.text}`}>
                        {value}
                    </p>
                    {sublabel && (
                        <p className="mt-0.5 text-xs text-gray-400">
                            {sublabel}
                        </p>
                    )}
                </div>
                <div
                    className={`flex h-9 w-9 items-center justify-center rounded-lg ${s.iconBg} ${s.iconText}`}
                >
                    {icon}
                </div>
            </div>
        </div>
    );
}

function StatusBreakdown({ breakdown }) {
    const statuses = [
        { key: "open", label: "Open", color: "bg-yellow-400" },
        { key: "claimed", label: "Claimed", color: "bg-blue-400" },
        { key: "pending", label: "Pending", color: "bg-purple-400" },
        { key: "resolved", label: "Resolved", color: "bg-green-400" },
        { key: "closed", label: "Closed", color: "bg-gray-400" },
    ];

    const total = Object.values(breakdown).reduce((sum, n) => sum + n, 0);
    if (total === 0) return null;

    return (
        <div className="rounded-lg border border-gray-200 bg-white p-4">
            <h3 className="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                Status Breakdown
            </h3>
            <div className="flex h-2.5 overflow-hidden rounded-full bg-gray-100">
                {statuses.map(({ key, color }) => {
                    const count = breakdown[key] ?? 0;
                    const pct = total > 0 ? (count / total) * 100 : 0;
                    return pct > 0 ? (
                        <div
                            key={key}
                            className={color}
                            style={{ width: `${pct}%` }}
                            title={`${key}: ${count}`}
                        />
                    ) : null;
                })}
            </div>
            <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1">
                {statuses.map(({ key, label, color }) => {
                    const count = breakdown[key] ?? 0;
                    return (
                        <div
                            key={key}
                            className="flex items-center gap-1.5 text-xs text-gray-600"
                        >
                            <span
                                className={`h-2 w-2 rounded-full ${color}`}
                            />
                            {label}: {count}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

function PriorityBreakdown({ breakdown }) {
    const priorities = [
        { key: "urgent", label: "Urgent", color: "text-red-600" },
        { key: "high", label: "High", color: "text-orange-600" },
        { key: "normal", label: "Normal", color: "text-blue-600" },
        { key: "low", label: "Low", color: "text-gray-500" },
    ];

    const hasData = priorities.some((p) => (breakdown[p.key] ?? 0) > 0);
    if (!hasData) return null;

    return (
        <div className="rounded-lg border border-gray-200 bg-white p-4">
            <h3 className="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                Open by Priority
            </h3>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {priorities.map(({ key, label, color }) => (
                    <div key={key} className="text-center">
                        <p className={`text-xl font-bold ${color}`}>
                            {breakdown[key] ?? 0}
                        </p>
                        <p className="text-xs text-gray-400">{label}</p>
                    </div>
                ))}
            </div>
        </div>
    );
}

function StatsWidgetSkeleton({ compact }) {
    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div className="h-4 w-32 animate-pulse rounded bg-gray-200" />
                <div className="h-5 w-5 animate-pulse rounded bg-gray-200" />
            </div>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                {[0, 1, 2].map((i) => (
                    <div
                        key={i}
                        className="h-24 animate-pulse rounded-xl border border-gray-200 bg-gray-100"
                    />
                ))}
            </div>
            {!compact && (
                <div className="h-16 animate-pulse rounded-lg border border-gray-200 bg-gray-100" />
            )}
        </div>
    );
}

/* --- Utilities --- */

function formatResponseTime(minutes) {
    if (minutes === null || minutes === undefined) return "—";

    if (minutes < 1) return "< 1m";
    if (minutes < 60) return `${Math.round(minutes)}m`;

    const hours = Math.floor(minutes / 60);
    const mins = Math.round(minutes % 60);

    if (hours < 24) {
        return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
    }

    const days = Math.floor(hours / 24);
    const remainingHours = hours % 24;
    return remainingHours > 0
        ? `${days}d ${remainingHours}h`
        : `${days}d`;
}

/* --- Icons --- */

function RefreshIcon({ spinning }) {
    return (
        <svg
            className={`h-4 w-4 ${spinning ? "animate-spin" : ""}`}
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={2}
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
            />
        </svg>
    );
}

function GaugeIcon() {
    return (
        <svg
            className="h-5 w-5"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={1.8}
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M3 18a9 9 0 0118 0M12 18l4-6m-4 6a3 3 0 11-6 0 3 3 0 016 0z"
            />
        </svg>
    );
}

function ClockIcon() {
    return (
        <svg
            className="h-5 w-5"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={1.8}
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
            />
        </svg>
    );
}

function InboxIcon() {
    return (
        <svg
            className="h-5 w-5"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={1.8}
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-3.586a1 1 0 00-.707.293l-1 1a1 1 0 01-.707.293h-4a1 1 0 01-.707-.293l-1-1A1 1 0 007.586 13H4"
            />
        </svg>
    );
}
