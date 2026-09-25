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
            'topics' => $this->topics ?? [],
            'problem_types' => $this->problem_types ?? [],
            'age_min_months' => $this->age_min_months,
            'age_max_months' => $this->age_max_months,
            'audience' => $this->audience,
            'language' => $this->language,
            'evidence_level' => $this->evidence_level,
            'publisher' => $this->publisher,
            'source_url' => $this->source_url,
            'published_at' => $this->published_at?->toDateString(),
            'reviewed_at' => $this->reviewed_at?->toDateString(),
            'is_approved' => (bool) $this->is_approved,
            'mime_type'  => $this->mime_type,
            'status'     => $this->status,
            'chunk_count'=> $this->chunks()->count(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
