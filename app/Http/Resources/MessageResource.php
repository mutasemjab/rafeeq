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
            'client_message_id' => data_get($this->metadata, 'client_message_id'),
            'user_message_id' => data_get($this->metadata, 'user_message_id', $this->role === 'user' ? $this->id : $this->reply_to_message_id),
            'delivery_status' => data_get($this->metadata, 'delivery_status'),
            'language' => data_get($this->metadata, 'language'),
            'sources' => $this->safeSources(),
            'response_type' => data_get($this->metadata, 'response_type', 'answer'),
            'domain' => data_get($this->metadata, 'turn_plan.domain'),
            'missing_information' => data_get($this->metadata, 'turn_plan.missing_fields', []),
            'next_action' => data_get($this->metadata, 'next_action'),
            'suggested_questions' => data_get($this->metadata, 'suggested_questions', []),
            'follow_up' => data_get($this->metadata, 'follow_up'),
            'evidence' => array_merge([
                'internal_search_performed' => false,
                'internal_search_completed_before_web' => false,
                'hosted_web_search_requested' => false,
                'search_order' => [],
                'internal_sources' => 0,
                'web_sources' => 0,
                'used_web_search' => false,
            ], (array) data_get($this->metadata, 'evidence', [])),
            'case_state' => data_get($this->metadata, 'case_state'),
            'safety_level' => data_get($this->metadata, 'safety.level', 'routine'),
            'safety_flags' => $this->safety_flags ?? [],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function safeSources(): mixed
    {
        if (! is_array($this->sources)) {
            return $this->sources;
        }

        return array_map(function ($source) {
            if (! is_array($source)) {
                return $source;
            }
            foreach (['url', 'file_url'] as $key) {
                $url = (string) ($source[$key] ?? '');
                if (($source['source_type'] ?? null) === 'chat_attachment'
                    || preg_match('#/storage/(?:app/)?(?:chat-attachments/|children/[^/]+/documents/)#i', rawurldecode($url))) {
                    unset($source[$key]);
                }
            }

            return $source;
        }, $this->sources);
    }
}
