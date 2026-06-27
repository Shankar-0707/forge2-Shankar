<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    /**
     * Build all dashboard metrics for the given organization.
     * Results are cached for 5 minutes per organization.
     *
     * @param int $orgId
     * @return array{
     *     tickets_by_status: array<string, int>,
     *     tickets_by_priority: array<string, int>,
     *     average_first_response_time_seconds: float|null,
     *     sla_breach_rate: float,
     *     daily_ticket_volume: array<int, array{date: string, count: int}>
     * }
     */
    public function getMetrics(int $orgId): array
    {
        return Cache::remember(
            "dashboard:metrics:org:{$orgId}",
            now()->addMinutes(5),
            fn () => [
                'tickets_by_status'                  => $this->getTicketsByStatus($orgId),
                'tickets_by_priority'                => $this->getTicketsByPriority($orgId),
                'average_first_response_time_seconds'=> $this->getAverageFirstResponseTime($orgId),
                'sla_breach_rate'                    => $this->getSlaBreachRate($orgId),
                'daily_ticket_volume'                => $this->getDailyTicketVolume($orgId),
            ]
        );
    }

    /**
     * @return array<string, int>
     */
    private function getTicketsByStatus(int $orgId): array
    {
        return Ticket::query()
            ->where('organization_id', $orgId)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->orderBy('status')
            ->pluck('count', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function getTicketsByPriority(int $orgId): array
    {
        return Ticket::query()
            ->where('organization_id', $orgId)
            ->select('priority', DB::raw('COUNT(*) as count'))
            ->groupBy('priority')
            ->orderBy('priority')
            ->pluck('count', 'priority')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Average first-response time in seconds across all tickets
     * that have been responded to. Returns null if no data.
     */
    private function getAverageFirstResponseTime(int $orgId): ?float
    {
        $driver = DB::getDriverName();

        $sql = match ($driver) {
            'sqlite'  => 'AVG((julianday(first_response_at) - julianday(created_at)) * 86400)',
            'pgsql'   => 'AVG(EXTRACT(EPOCH FROM (first_response_at - created_at)))',
            default   => 'AVG(TIMESTAMPDIFF(SECOND, created_at, first_response_at))',
        };

        $value = Ticket::query()
            ->where('organization_id', $orgId)
            ->whereNotNull('first_response_at')
            ->selectRaw("{$sql} as avg_seconds")
            ->value('avg_seconds');

        return $value !== null ? round((float) $value, 2) : null;
    }

    /**
     * Percentage of tickets that have breached SLA.
     * A ticket is considered breached when its SLA deadline
     * has passed and it has not yet been resolved or closed.
     */
    private function getSlaBreachRate(int $orgId): float
    {
        $totalWithSla = Ticket::query()
            ->where('organization_id', $orgId)
            ->whereNotNull('sla_due_at')
            ->count();

        if ($totalWithSla === 0) {
            return 0.0;
        }

        $breached = Ticket::query()
            ->where('organization_id', $orgId)
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->whereNotIn('status', ['resolved', 'closed'])
            ->count();

        return round(($breached / $totalWithSla) * 100, 2);
    }

    /**
     * Daily ticket creation volume for the last 30 days.
     * Every day in the range is included, even if zero tickets.
     *
     * @return array<int, array{date: string, count: int}>
     */
    private function getDailyTicketVolume(int $orgId): array
    {
        $startDate = now()->subDays(29)->startOfDay();
        $endDate   = now()->endOfDay();

        // Seed all 30 days with 0 so the frontend always has a full range
        $allDays = Collection::make();
        $cursor  = $startDate->copy();

        while ($cursor <= $endDate) {
            $allDays[$cursor->format('Y-m-d')] = 0;
            $cursor->addDay();
        }

        // Query actual counts
        $driver = DB::getDriverName();

        $dateExpression = match ($driver) {
            'pgsql' => 'created_at::date',
            default => 'DATE(created_at)',
        };

        $actualCounts = Ticket::query()
            ->where('organization_id', $orgId)
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->selectRaw("{$dateExpression} as date, COUNT(*) as count")
            ->groupByRaw($dateExpression)
            ->orderByRaw($dateExpression)
            ->pluck('count', 'date')
            ->map(fn ($count) => (int) $count);

        // Merge actual counts over the zero-seeded days
        return $allDays
            ->merge($actualCounts)
            ->map(fn (int $count, string $date) => ['date' => $date, 'count' => $count])
            ->values()
            ->all();
    }

    /**
     * Clear the cached metrics for a specific organization.
     * Call this when tickets are created/updated/deleted.
     */
    public function clearCache(int $orgId): void
    {
        Cache::forget("dashboard:metrics:org:{$orgId}");
    }
}
