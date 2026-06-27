function formatRelativeTime(dateString) {
  const now = Date.now();
  const then = new Date(dateString).getTime();
  const diff = Math.floor((now - then) / 1000);

  if (diff < 60) return "just now";
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;

  return new Intl.DateTimeFormat("en-US", {
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  }).format(new Date(dateString));
}

const ACTIVITY_ICONS = {
  created: "🆕",
  status_changed: "🔄",
  assigned: "👤",
  unassigned: "👤",
  priority_changed: "⚠️",
  comment_added: "💬",
  tag_added: "🏷️",
  tag_removed: "🏷️",
  closed: "✅",
  reopened: "🔓",
};

function getIcon(type) {
  return ACTIVITY_ICONS[type] ?? "•";
}

function describeActivity(activity) {
  const { type, user, metadata } = activity;
  const who = user?.name ?? "System";

  switch (type) {
    case "created":
      return <><strong>{who}</strong> created this ticket</>;
    case "status_changed":
      return (
        <>
          <strong>{who}</strong> changed status from{" "}
          <span className="font-medium text-gray-700">{metadata?.from}</span> to{" "}
          <span className="font-medium text-gray-700">{metadata?.to}</span>
        </>
      );
    case "priority_changed":
      return (
        <>
          <strong>{who}</strong> changed priority from{" "}
          <span className="font-medium text-gray-700">{metadata?.from}</span> to{" "}
          <span className="font-medium text-gray-700">{metadata?.to}</span>
        </>
      );
    case "assigned":
      return <><strong>{who}</strong> assigned this ticket to <span className="font-medium text-gray-700">{metadata?.assignee ?? "someone"}</span></>;
    case "unassigned":
      return <><strong>{who}</strong> unassigned this ticket</>;
    case "comment_added":
      return <><strong>{who}</strong> added a {metadata?.comment_type ?? "comment"}</>;
    case "tag_added":
      return <><strong>{who}</strong> added tag <span className="font-medium text-gray-700">{metadata?.tag}</span></>;
    case "tag_removed":
      return <><strong>{who}</strong> removed tag <span className="font-medium text-gray-700">{metadata?.tag}</span></>;
    case "closed":
      return <><strong>{who}</strong> closed this ticket</>;
    case "reopened":
      return <><strong>{who}</strong> reopened this ticket</>;
    default:
      return activity.description ?? <><strong>{who}</strong> performed an action</>;
  }
}

export default function ActivityLog({ activities }) {
  if (!activities || activities.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center py-12 text-center">
        <div className="mb-3 text-4xl opacity-40">📋</div>
        <p className="text-sm font-medium text-gray-500">No activity yet</p>
        <p className="mt-1 text-xs text-gray-400">
          Actions on this ticket will appear here.
        </p>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-2xl">
      <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">
        Activity Log
      </h2>
      <ol className="relative">
        {activities.map((activity, index) => (
          <li
            key={activity.id ?? index}
            className="relative flex gap-3 pb-6 last:pb-0"
          >
            {/* Timeline line */}
            {index < activities.length - 1 && (
              <span
                className="absolute left-[15px] top-8 h-full w-px bg-gray-200"
                aria-hidden="true"
              />
            )}

            {/* Icon */}
            <div className="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-sm">
              {getIcon(activity.type)}
            </div>

            {/* Content */}
            <div className="min-w-0 flex-1 pt-0.5">
              <p className="text-sm text-gray-700">
                {describeActivity(activity)}
              </p>
              <p
                className="mt-0.5 text-xs text-gray-400"
                title={new Date(activity.created_at).toLocaleString()}
              >
                {formatRelativeTime(activity.created_at)}
              </p>
            </div>
          </li>
        ))}
      </ol>
    </div>
  );
}
