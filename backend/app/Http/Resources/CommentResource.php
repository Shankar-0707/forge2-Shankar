<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'body'         => $this->body,
            'is_internal'  => $this->is_internal,
            'user' => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ],
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
