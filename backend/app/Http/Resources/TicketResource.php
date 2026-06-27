<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $slaStatus = 'none';
        $slaRemainingMinutes = null;

        if ($this->sla_due_at) {
            $remaining = now()->diffInMinutes($this->sla_due_at, false);

            if ($remaining < 0) {
                $slaStatus = 'breached';
                $slaRemainingMinutes = abs($remaining);
            } elseif ($remaining <= 120) {
                $slaStatus = 'at_risk';
                $slaRemainingMinutes = $remaining;
            } else {
                $slaStatus = 'on_track';
                $slaRemainingMinutes = $remaining;
            }
        }

        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'category' => $this->category,
            'assignee' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
                'avatar_url' => $this->assignee->avatar_url ?? null,
            ]),
            'requester' => $this->whenLoaded('requester', fn () => [
                'id' => $this->requester->id,
                'name' => $this->requester->name,
                'avatar_url' => $this->requester->avatar_url ?? null,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'resolved_at' => $this->resolved_at,
            'sla_due_at' => $this->sla_due_at,
            'sla_status' => $slaStatus,
            'sla_remaining_minutes' => $slaRemainingMinutes,
        ];
    }
}
