<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\DashboardMetricsResource;
use App\Services\DashboardMetricsService;
use Illuminate\Http\JsonResponse;

class DashboardMetricsController extends Controller
{
    public function __construct(
        private readonly DashboardMetricsService $metricsService,
    ) {}

    /**
     * Return aggregated dashboard metrics for the authenticated user's organization.
     */
    public function index(): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        $metrics = $this->metricsService->getMetrics($orgId);

        return (new DashboardMetricsResource($metrics))
            ->response()
            ->setStatusCode(200);
    }
}
