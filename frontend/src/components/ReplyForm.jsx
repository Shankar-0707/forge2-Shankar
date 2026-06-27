import { useState, useRef, useCallback } from "react";

const MIN_BODY_LENGTH = 1;

export default function ReplyForm({ onReply, disabled = false }) {
  const [mode, setMode] = useState("public"); // 'public' | 'internal'
  const [body, setBody] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);
  const textareaRef = useRef(null);

  const isValid = body.trim().length >= MIN_BODY_LENGTH;

  const handleSubmit = useCallback(
    async (e) => {
      e?.preventDefault();
      if (!isValid || submitting || disabled) return;

      try {
        setError(null);
        setSubmitting(true);
        await onReply(mode, body.trim());
        setBody("");
        setMode("public");
      } catch (err) {
        setError(err.message ?? "Failed to post reply. Please try again.");
      } finally {
        setSubmitting(false);
      }
    },
    [body, mode, isValid, submitting, disabled, onReply]
  );

  const handleKeyDown = useCallback(
    (e) => {
      // Ctrl/Cmd + Enter to submit
      if ((e.metaKey || e.ctrlKey) && e.key === "Enter") {
        e.preventDefault();
        handleSubmit();
      }
    },
    [handleSubmit]
  );

  const isInternal = mode === "internal";

  return (
    <form onSubmit={handleSubmit}>
      {/* Mode toggle */}
      <div className="mb-2 flex items-center gap-1">
        <button
          type="button"
          onClick={() => setMode("public")}
          className={`rounded-md px-3 py-1 text-xs font-semibold uppercase tracking-wide transition-colors ${
            mode === "public"
              ? "bg-indigo-600 text-white"
              : "text-gray-500 hover:bg-gray-100 hover:text-gray-700"
          }`}
        >
          Public Reply
        </button>
        <button
          type="button"
          onClick={() => setMode("internal")}
          className={`rounded-md px-3 py-1 text-xs font-semibold uppercase tracking-wide transition-colors ${
            mode === "internal"
              ? "bg-yellow-500 text-white"
              : "text-gray-500 hover:bg-gray-100 hover:text-gray-700"
          }`}
        >
          Internal Note
        </button>
        <span className="ml-auto text-xs text-gray-400">
          <kbd className="rounded border border-gray-300 bg-gray-50 px-1 py-0.5 text-[10px] font-medium">
            ⌘
          </kbd>
          +
          <kbd className="ml-0.5 rounded border border-gray-300 bg-gray-50 px-1 py-0.5 text-[10px] font-medium">
            ↵
          </kbd>{" "}
          to send
        </span>
      </div>

      {/* Textarea wrapper — color-coded by mode */}
      <div
        className={`rounded-lg border-2 transition-colors ${
          isInternal
            ? "border-yellow-200 focus-within:border-yellow-400"
            : "border-gray-200 focus-within:border-indigo-400"
        }`}
      >
        <textarea
          ref={textareaRef}
          value={body}
          onChange={(e) => setBody(e.target.value)}
          onKeyDown={handleKeyDown}
          disabled={disabled || submitting}
          rows={3}
          placeholder={
            isInternal
              ? "Write an internal note (visible to your team only)…"
              : "Write a public reply (visible to the requester)…"
          }
          className="block w-full resize-y rounded-lg border-0 px-4 py-3 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
        />
      </div>

      {/* Footer: error + actions */}
      <div className="mt-2 flex items-center justify-between">
        <div className="min-w-0 flex-1">
          {error && (
            <p className="truncate text-xs text-red-600" title={error}>
              {error}
            </p>
          )}
          {!error && (
            <p className="text-xs text-gray-400">
              {isInternal
                ? "🔒 Only agents and admins can see internal notes."
                : "📩 This reply will be visible to the ticket requester."}
            </p>
          )}
        </div>
        <button
          type="submit"
          disabled={!isValid || submitting || disabled}
          className={`ml-3 inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors disabled:cursor-not-allowed disabled:opacity-50 ${
            isInternal
              ? "bg-yellow-500 hover:bg-yellow-600"
              : "bg-indigo-600 hover:bg-indigo-700"
          }`}
        >
          {submitting ? (
            <>
              <svg
                className="h-4 w-4 animate-spin"
                viewBox="0 0 24 24"
                fill="none"
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
              Sending…
            </>
          ) : (
            <>
              {isInternal ? "Post Note" : "Send Reply"}
            </>
          )}
        </button>
      </div>
    </form>
  );
}
