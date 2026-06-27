import { useState, useEffect, useCallback } from "react";

const API_BASE = import.meta.env.VITE_API_URL || "/api";

/**
 * TicketActions — claim and reassign controls for a single ticket.
 *
 * Props:
 *   ticket      — the ticket object (must include id, status, assignee, etc.)
 *   currentUser — the logged-in user object (id, role, organization_id)
 *   onUpdated   — optional callback(ticket) fired after a successful action
 */
export default function TicketActions({ ticket, currentUser, onUpdated }) {
    const [assignableUsers, setAssignableUsers] = useState([]);
    const [loading, setLoading] = useState(false);
    const [fetchingUsers, setFetchingUsers] = useState(false);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);
    const [showReassign, setShowReassign] = useState(false);
    const [selectedUserId, setSelectedUserId] = useState("");

    const canManage =
        currentUser?.role === "admin" || currentUser?.role === "agent";

    const isAssignedToMe = ticket?.assigned_to === currentUser?.id;
    const isUnassigned = !ticket?.assigned_to;

    const fetchAssignableUsers = useCallback(async () => {
        setFetchingUsers(true);
        try {
            const res = await fetch(`${API_BASE}/users/assignable`, {
                credentials: "include",
                headers: { Accept: "application/json" },
            });
            if (!res.ok) throw new Error("Failed to load team members");
            const data = await res.json();
            setAssignableUsers(data.data ?? data);
        } catch (err) {
            console.error("Error fetching assignable users:", err);
        } finally {
            setFetchingUsers(false);
        }
    }, []);

    useEffect(() => {
        if (canManage) {
            fetchAssignableUsers();
        }
    }, [canManage, fetchAssignableUsers]);

    // Clear transient messages after 4 seconds
    useEffect(() => {
        if (success || error) {
            const timer = setTimeout(() => {
                setSuccess(null);
                setError(null);
            }, 4000);
            return () => clearTimeout(timer);
        }
    }, [success, error]);

    const handleClaim = async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await fetch(`${API_BASE}/tickets/${ticket.id}/claim`, {
                method: "POST",
                credentials: "include",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute("content"),
                },
            });

            const data = await res.json();

            if (!res.ok) {
                throw new Error(data.message || "Failed to claim ticket");
            }

            setSuccess("Ticket claimed successfully.");
            onUpdated?.(data.ticket);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    const handleReassign = async () => {
        if (!selectedUserId) {
            setError("Please select a team member.");
            return;
        }

        setLoading(true);
        setError(null);
        try {
            const res = await fetch(
                `${API_BASE}/tickets/${ticket.id}/reassign`,
                {
                    method: "POST",
                    credentials: "include",
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute("content"),
                    },
                    body: JSON.stringify({ user_id: parseInt(selectedUserId, 10) }),
                }
            );

            const data = await res.json();

            if (!res.ok) {
                throw new Error(data.message || "Failed to reassign ticket");
            }

            setSuccess(data.message || "Ticket reassigned.");
            setShowReassign(false);
            setSelectedUserId("");
            onUpdated?.(data.ticket);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    if (!canManage) {
        return (
            <div className="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-500">
                {ticket?.assignee ? (
                    <span>
                        Assigned to{" "}
                        <strong className="text-gray-700">
                            {ticket.assignee.name}
                        </strong>
                    </span>
                ) : (
                    <span>This ticket is unassigned.</span>
                )}
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Current assignee display */}
            <div className="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-4">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700">
                    {ticket?.assignee
                        ? ticket.assignee.name.charAt(0).toUpperCase()
                        : "?"}
                </div>
                <div className="flex-1">
                    <p className="text-xs font-medium uppercase tracking-wide text-gray-500">
                        Assigned To
                    </p>
                    <p className="text-sm font-semibold text-gray-900">
                        {ticket?.assignee?.name ?? "Unassigned"}
                    </p>
                </div>
                {ticket?.status && (
                    <span
                        className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                            ticket.status === "open"
                                ? "bg-yellow-100 text-yellow-800"
                                : ticket.status === "claimed"
                                ? "bg-blue-100 text-blue-800"
                                : ticket.status === "resolved"
                                ? "bg-green-100 text-green-800"
                                : "bg-gray-100 text-gray-700"
                        }`}
                    >
                        {ticket.status}
                    </span>
                )}
            </div>

            {/* Action buttons */}
            <div className="flex flex-wrap gap-2">
                {isUnassigned && (
                    <button
                        onClick={handleClaim}
                        disabled={loading}
                        className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {loading ? (
                            <>
                                <Spinner />
                                Claiming...
                            </>
                        ) : (
                            <>
                                <HandIcon />
                                Claim Ticket
                            </>
                        )}
                    </button>
                )}

                {!isUnassigned && isAssignedToMe && (
                    <span className="inline-flex items-center gap-1.5 rounded-md bg-green-50 px-3 py-2 text-sm font-medium text-green-700">
                        <CheckIcon />
                        Claimed by you
                    </span>
                )}

                <button
                    onClick={() => setShowReassign((v) => !v)}
                    disabled={loading}
                    className="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <SwapIcon />
                    {isUnassigned ? "Assign..." : "Reassign..."}
                </button>
            </div>

            {/* Reassign panel */}
            {showReassign && (
                <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <label
                        htmlFor="reassign-select"
                        className="mb-2 block text-sm font-medium text-gray-700"
                    >
                        Select team member
                    </label>
                    <div className="flex gap-2">
                        <select
                            id="reassign-select"
                            value={selectedUserId}
                            onChange={(e) => setSelectedUserId(e.target.value)}
                            disabled={fetchingUsers}
                            className="flex-1 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                            <option value="">
                                {fetchingUsers
                                    ? "Loading..."
                                    : "Choose a team member..."}
                            </option>
                            {assignableUsers
                                .filter((u) => u.id !== currentUser?.id)
                                .map((user) => (
                                    <option key={user.id} value={user.id}>
                                        {user.name} ({user.role})
                                    </option>
                                ))}
                        </select>
                        <button
                            onClick={handleReassign}
                            disabled={loading || !selectedUserId}
                            className="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {loading ? "Assigning..." : "Confirm"}
                        </button>
                        <button
                            onClick={() => {
                                setShowReassign(false);
                                setSelectedUserId("");
                            }}
                            className="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            )}

            {/* Feedback messages */}
            {success && (
                <div className="flex items-center gap-2 rounded-md border border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-700">
                    <CheckIcon />
                    {success}
                </div>
            )}

            {error && (
                <div className="flex items-center gap-2 rounded-md border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700">
                    <AlertIcon />
                    {error}
                </div>
            )}
        </div>
    );
}

/* --- Small inline icon components --- */

function Spinner() {
    return (
        <svg
            className="h-4 w-4 animate-spin"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle
                className="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                strokeWidth="4"
            />
            <path
                className="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
            />
        </svg>
    );
}

function HandIcon() {
    return (
        <svg
            className="h-4 w-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={1.8}
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M7 11V7a2 2 0 014 0v4M11 11V5a2 2 0 014 0v6M15 11V7a2 2 0 014 0v6a7 7 0 01-7 7h-2a7 7 0 01-6-3l-2-3"
            />
        </svg>
    );
}

function SwapIcon() {
    return (
        <svg
            className="h-4 w-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={1.8}
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"
            />
        </svg>
    );
}

function CheckIcon() {
    return (
        <svg
            className="h-4 w-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={2}
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M5 13l4 4L19 7"
            />
        </svg>
    );
}

function AlertIcon() {
    return (
        <svg
            className="h-4 w-4 shrink-0"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={2}
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"
            />
        </svg>
    );
}
