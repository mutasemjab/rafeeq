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
            'domain' => data_get($this->metadata, 'turn_plan.domain'),
            'missing_information' => data_get($this->metadata, 'turn_plan.missing_fields', []),
            'next_action' => data_get($this->metadata, 'next_action'),
            'suggested_questions' => data_get($this->metadata, 'suggested_questions', []),
            'follow_up' => data_get($this->metadata, 'follow_up'),
            'evidence' => data_get($this->metadata, 'evidence', [
                'internal_sources' => 0,
                'web_sources' => 0,
                'used_web_search' => false,
            ]),
            'case_state' => data_get($this->metadata, 'case_state'),
            'safety_level' => data_get($this->metadata, 'safety.level', 'routine'),
            'safety_flags' => $this->safety_flags ?? [],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
