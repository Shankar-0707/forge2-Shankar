import { forwardRef, useEffect, useRef } from "react";

function formatTimestamp(dateString) {
  return new Intl.DateTimeFormat("en-US", {
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  }).format(new Date(dateString));
}

function getInitials(name) {
  return (name ?? "?")
    .split(" ")
    .map((n) => n[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();
}

const AVATAR_COLORS = [
  "bg-indigo-100 text-indigo-700",
  "bg-emerald-100 text-emerald-700",
  "bg-amber-100 text-amber-700",
  "bg-rose-100 text-rose-700",
  "bg-sky-100 text-sky-700",
  "bg-violet-100 text-violet-700",
];

function getAvatarColor(name) {
  const hash = (name ?? "").split("").reduce((a, c) => a + c.charCodeAt(0), 0);
  return AVATAR_COLORS[hash % AVATAR_COLORS.length];
}

function MessageBubble({ comment }) {
  const isInternal = comment.type === "internal";
  const author = comment.author ?? {};
  const authorName = author.name ?? "Unknown User";

  return (
    <div className="flex gap-3">
      {/* Avatar */}
      <div
        className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold ${getAvatarColor(
          authorName
        )}`}
      >
        {getInitials(authorName)}
      </div>

      {/* Bubble */}
      <div className="min-w-0 flex-1">
        <div className="mb-1 flex items-center gap-2">
          <span className="text-sm font-semibold text-gray-900">
            {authorName}
          </span>
          {isInternal && (
            <span className="inline-flex items-center rounded bg-yellow-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-yellow-800">
              Internal Note
            </span>
          )}
          <span className="text-xs text-gray-400">
            {formatTimestamp(comment.created_at)}
          </span>
        </div>
        <div
          className={`rounded-lg px-4 py-3 ${
            isInternal
              ? "bg-yellow-50 ring-1 ring-yellow-200"
              : "bg-white ring-1 ring-gray-200"
          }`}
        >
          <p className="whitespace-pre-wrap break-words text-sm text-gray-800">
            {comment.body}
          </p>
        </div>
      </div>
    </div>
  );
}

const ConversationThread = forwardRef(function ConversationThread(
  { comments, ticketDescription, ticketAuthor, ticketCreatedAt },
  _ref
) {
  const scrollRef = useRef(null);

  // Auto-scroll to bottom when new comments arrive
  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }
  }, [comments]);

  const authorName = ticketAuthor?.name ?? "Unknown";

  return (
    <div ref={scrollRef} className="mx-auto max-w-3xl space-y-6 pb-4">
      {/* Original ticket message */}
      <div className="flex gap-3">
        <div
          className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold ${getAvatarColor(
            authorName
          )}`}
        >
          {getInitials(authorName)}
        </div>
        <div className="min-w-0 flex-1">
          <div className="mb-1 flex items-center gap-2">
            <span className="text-sm font-semibold text-gray-900">
              {authorName}
            </span>
            <span className="inline-flex items-center rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-blue-800">
              Original Request
            </span>
            <span className="text-xs text-gray-400">
              {ticketCreatedAt ? formatTimestamp(ticketCreatedAt) : ""}
            </span>
          </div>
          <div className="rounded-lg bg-indigo-50 px-4 py-3 ring-1 ring-indigo-100">
            <p className="whitespace-pre-wrap break-words text-sm text-gray-800">
              {ticketDescription ?? "(No description provided)"}
            </p>
          </div>
        </div>
      </div>

      {/* Comments */}
      {comments.length > 0 && (
        <>
          <div className="flex items-center gap-3">
            <div className="h-px flex-1 bg-gray-200" />
            <span className="text-xs font-medium uppercase tracking-wide text-gray-400">
              {comments.length} {comments.length === 1 ? "Reply" : "Replies"}
            </span>
            <div className="h-px flex-1 bg-gray-200" />
          </div>
          {comments.map((comment) => (
            <MessageBubble key={comment.id} comment={comment} />
          ))}
        </>
      )}

      {comments.length === 0 && !ticketDescription && (
        <div className="flex flex-col items-center justify-center py-12 text-center">
          <div className="mb-3 text-4xl opacity-40">💬</div>
          <p className="text-sm font-medium text-gray-500">
            No messages yet
          </p>
          <p className="mt-1 text-xs text-gray-400">
            Be the first to reply to this ticket.
          </p>
        </div>
      )}
    </div>
  );
});

export default ConversationThread;
