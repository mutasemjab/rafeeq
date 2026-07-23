<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'role' => $this->role,
            'content' => $this->content,
            'sources' => $this->sources,
            'response_type' => data_get($this->metadata, 'response_type', 'answer'),
            'safety_flags' => $this->safety_flags ?? [],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
