<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'open_tickets'         => $this['open_tickets'],
            'in_progress_tickets'  => $this['in_progress_tickets'],
            'resolved_tickets'     => $this['resolved_tickets'],
            'urgent_tickets'       => $this['urgent_tickets'],
            'total_tickets'        => $this['total_tickets'],
            'recent_tickets'       => TicketResource::collection($this['recent_tickets']),
            'categories'           => CategoryResource::collection($this['categories']),
        ];
    }
}
