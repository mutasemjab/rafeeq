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
            'key'        => $this->key,
            'content'    => $this->content,
            'confidence' => $this->confidence,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
