<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChildMemoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'child_id'   => $this->child_id,
            'key'        => $this->memory_key,
            'type'       => $this->type,
            'title'      => $this->title,
            'content'    => $this->content,
            'confidence' => $this->confidence,
            'status'     => $this->status,
            'last_confirmed_at' => $this->last_confirmed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
