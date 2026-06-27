<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    /**
     * Return ticket metrics for the auth user's organization dashboard.
     *
     * - SLA breach rate (%)
     * - Average first-response time (minutes)
     * - Open ticket count
     * - Additional breakdowns for the widget
     */
    public function metrics(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $orgId = $user->organization_id;

        $slaThreshold = $user->organization->getSlaThresholdMinutes();

        // --- Open ticket count ---
        $openCount = Ticket::where('organization_id', $orgId)
            ->whereNotIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED])
            ->count();

        // --- Average first-response time (only tickets that have been responded to) ---
        $avgResponseSeconds = Ticket::where('organization_id', $orgId)
            ->whereNotNull('first_response_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, first_response_at)) as avg_seconds')
            ->value('avg_seconds');

        $avgResponseMinutes = $avgResponseSeconds ? round($avgResponseSeconds / 60, 1) : null;

        // --- SLA breach rate ---
        // A ticket is "breached" if:
        //   (a) it was responded to, but late — response took longer than the SLA threshold, OR
        //   (b) it has NOT been responded to yet, and it's already past the threshold window
        $totalTickets = Ticket::where('organization_id', $orgId)->count();

        $breachedCount = 0;

        if ($totalTickets > 0) {
            // (a) responded late
            $respondedLate = Ticket::where('organization_id', $orgId)
                ->whereNotNull('first_response_at')
                ->whereRaw('TIMESTAMPDIFF(MINUTE, created_at, first_response_at) > ?', [$slaThreshold])
                ->count();

            // (b) not responded and past SLA window
            $unrespondedOverdue = Ticket::where('organization_id', $orgId)
                ->whereNull('first_response_at')
                ->whereNotIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED])
                ->whereRaw('TIMESTAMPDIFF(MINUTE, created_at, NOW()) > ?', [$slaThreshold])
                ->count();

            $breachedCount = $respondedLate + $unrespondedOverdue;
        }

        $slaBreachRate = $totalTickets > 0
            ? round(($breachedCount / $totalTickets) * 100, 1)
            : 0.0;

        // --- Status breakdown for the widget ---
        $statusBreakdown = Ticket::where('organization_id', $orgId)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // --- Priority breakdown for open tickets ---
        $priorityBreakdown = Ticket::where('organization_id', $orgId)
            ->whereNotIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED])
            ->select('priority', DB::raw('COUNT(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();

        // --- Trend: tickets created in last 7 days ---
        $last7Days = Ticket::where('organization_id', $orgId)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return response()->json([
            'sla_breach_rate' => $slaBreachRate,
            'sla_breach_count' => $breachedCount,
            'sla_threshold_minutes' => $slaThreshold,
            'avg_first_response_minutes' => $avgResponseMinutes,
            'open_ticket_count' => $openCount,
            'total_tickets' => $totalTickets,
            'tickets_last_7_days' => $last7Days,
            'status_breakdown' => $statusBreakdown,
            'priority_breakdown' => $priorityBreakdown,
        ]);
    }
}
