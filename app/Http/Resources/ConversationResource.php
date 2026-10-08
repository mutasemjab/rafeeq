<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'child_id'      => $this->child_id,
            'person_profile_id' => $this->person_profile_id,
            'is_temporary' => $this->is_temporary,
            'expires_at' => $this->expires_at?->toISOString(),
            'summary'       => $this->summary,
            'active_domain' => $this->active_domain,
            'case_state'    => $this->case_state,
            'next_question' => $this->next_question,
            'source'        => $this->source,
            'status'        => $this->status,
            'message_count' => $this->message_count,
            'created_at'    => $this->created_at?->toISOString(),
            'updated_at'    => $this->updated_at?->toISOString(),
        ];
    }
}
