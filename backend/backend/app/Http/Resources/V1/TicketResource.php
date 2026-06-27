<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'subject'             => $this->subject,
            'description'         => $this->description,
            'status'              => $this->status,
            'priority'            => $this->priority,
            'organization_id'     => $this->organization_id,
            'requester'           => $this->whenLoaded('requester', fn() => [
                'id'    => $this->requester->id,
                'name'  => $this->requester->name,
                'email' => $this->requester->email,
            ]),
            'assignee'            => $this->whenLoaded('assignee', fn() => [
                'id'    => $this->assignee->id,
                'name'  => $this->assignee->name,
            ]),
            'team'                => $this->whenLoaded('team', fn() => [
                'id'   => $this->team->id,
                'name' => $this->team->name,
            ]),
            'first_response_at'   => $this->first_response_at?->toIso8601String(),
            'resolved_at'         => $this->resolved_at?->toIso8601String(),
            'created_at'          => $this->created_at->toIso8601String(),
            'updated_at'          => $this->updated_at->toIso8601String(),

            // SLA computed fields
            'sla' => [
                'policy' => $this->when(
                    $this->slaPolicy(),
                    fn() => [
                        'id'                     => $this->slaPolicy()->id,
                        'priority'               => $this->slaPolicy()->priority,
                        'response_time_limit'    => $this->slaPolicy()->response_time_limit,
                        'resolution_time_limit'  => $this->slaPolicy()->resolution_time_limit,
                    ]
                ),
                'response_time_remaining'         => $this->response_time_remaining,
                'resolution_time_remaining'       => $this->resolution_time_remaining,
                'response_time_remaining_format'  => $this->response_time_remaining_formatted,
                'resolution_time_remaining_format'=> $this->resolution_time_remaining_formatted,
                'response_sla_breached'           => $this->response_sla_breached,
                'resolution_sla_breached'         => $this->resolution_sla_breached,
            ],
        ];
    }
}
