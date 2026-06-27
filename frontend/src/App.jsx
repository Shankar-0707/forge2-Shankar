import { Routes, Route, Navigate } from 'react-router-dom'

/**
 * PulseDesk — Application Root
 *
 * Route structure:
 *   /               → redirect to /dashboard
 *   /login          → auth page (placeholder)
 *   /dashboard      → agent dashboard (placeholder)
 *   /tickets        → ticket list (placeholder)
 *   /tickets/:id    → ticket detail (placeholder)
 *   /admin          → admin settings (placeholder)
 *   *               → 404 fallback
 *
 * ProtectedRoute wrapper will be wired up once
 * Sanctum auth store is implemented.
 */

function Placeholder({ title, subtitle }) {
  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50">
      <div className="card max-w-md p-8 text-center">
        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-brand-100">
          <svg className="h-6 w-6 text-brand-600" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
          </svg>
        </div>
        <h1 className="text-xl font-bold text-slate-900">{title}</h1>
        {subtitle && <p className="mt-2 text-sm text-slate-500">{subtitle}</p>}
      </div>
    </div>
  )
}

function NotFound() {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-slate-50">
      <p className="text-6xl font-bold text-brand-600">404</p>
      <p className="mt-2 text-lg font-medium text-slate-700">Page not found</p>
      <a href="/" className="btn-primary mt-6">Go home</a>
    </div>
  )
}

export default function App() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/dashboard" replace />} />
      <Route path="/login" element={<Placeholder title="Login" subtitle="Sanctum auth flow coming next." />} />
      <Route path="/dashboard" element={<Placeholder title="Dashboard" subtitle="Ticket metrics & activity feed." />} />
      <Route path="/tickets" element={<Placeholder title="Tickets" subtitle="Filterable ticket inbox." />} />
      <Route path="/tickets/:id" element={<Placeholder title="Ticket Detail" subtitle="Conversation thread & actions." />} />
      <Route path="/admin" element={<Placeholder title="Admin" subtitle="Organization settings & users." />} />
      <Route path="*" element={<NotFound />} />
    </Routes>
  )
}
