<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array
 */
class DashboardMetricsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tickets_by_status'                   => $this->resource['tickets_by_status'],
            'tickets_by_priority'                 => $this->resource['tickets_by_priority'],
            'average_first_response_time_seconds' => $this->resource['average_first_response_time_seconds'],
            'sla_breach_rate'                     => $this->resource['sla_breach_rate'],
            'daily_ticket_volume'                 => $this->resource['daily_ticket_volume'],
        ];
    }
}
