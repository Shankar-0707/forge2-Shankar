<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'description'   => $this->description,
            'status'        => $this->status,
            'priority'      => $this->priority,
            'category'      => new CategoryResource($this->whenLoaded('category')),
            'creator'       => new UserResource($this->whenLoaded('creator')),
            'assignee'      => new UserResource($this->whenLoaded('assignee')),
            'comments_count'=> $this->whenCounted('comments'),
            'comments'      => TicketCommentResource::collection($this->whenLoaded('comments')),
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
