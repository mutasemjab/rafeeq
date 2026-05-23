<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class KnowledgeDocumentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'category'   => $this->category,
            'mime_type'  => $this->mime_type,
            'status'     => $this->status,
            'chunk_count'=> $this->chunks()->count(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
