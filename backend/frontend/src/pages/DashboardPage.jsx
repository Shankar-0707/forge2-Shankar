export default function DashboardPage() {
  return (
    <div className="min-h-screen bg-gray-50">
      <nav className="bg-white shadow-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between h-16">
            <div className="flex items-center">
              <span className="text-xl font-bold text-brand-600">PulseDesk</span>
            </div>
          </div>
        </div>
      </nav>

      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
        <p className="mt-2 text-gray-600">Welcome to your support dashboard.</p>

        <div className="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
          <div className="bg-white overflow-hidden shadow rounded-lg p-6">
            <dt className="text-sm font-medium text-gray-500 truncate">Open Tickets</dt>
            <dd className="mt-1 text-3xl font-semibold text-gray-900">—</dd>
          </div>
          <div className="bg-white overflow-hidden shadow rounded-lg p-6">
            <dt className="text-sm font-medium text-gray-500 truncate">Unassigned</dt>
            <dd className="mt-1 text-3xl font-semibold text-gray-900">—</dd>
          </div>
          <div className="bg-white overflow-hidden shadow rounded-lg p-6">
            <dt className="text-sm font-medium text-gray-500 truncate">Avg Response</dt>
            <dd className="mt-1 text-3xl font-semibold text-gray-900">—</dd>
          </div>
          <div className="bg-white overflow-hidden shadow rounded-lg p-6">
            <dt className="text-sm font-medium text-gray-500 truncate">CSAT Score</dt>
            <dd className="mt-1 text-3xl font-semibold text-gray-900">—</dd>
          </div>
        </div>
      </main>
    </div>
  )
}
