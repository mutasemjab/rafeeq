<?php

namespace App\Http\Resources;

use App\Models\KnowledgeDocument;
use Illuminate\Support\Arr;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'role' => $this->role,
            'content' => $this->role === 'assistant' ? $this->answerText() : $this->content,
            'client_message_id' => data_get($this->metadata, 'client_message_id'),
            'user_message_id' => data_get($this->metadata, 'user_message_id', $this->role === 'user' ? $this->id : $this->reply_to_message_id),
            'delivery_status' => data_get($this->metadata, 'delivery_status'),
            'language' => data_get($this->metadata, 'language'),
            'response_mode' => data_get($this->metadata, 'response_mode', 'fast'),
            'extra_thinking' => (bool) data_get($this->metadata, 'extra_thinking', false),
            'feedback' => $this->role === 'user' ? data_get($this->metadata, 'feedback') : null,
            'context_superseded' => (bool) data_get($this->metadata, 'context_superseded', false),
            'sources' => $this->safeSources($request),
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

    private function answerText(): string
    {
        // Keep the original cited answer on the message for quality review and audit.
        $text = preg_replace('/[ \t]*\[(?:KB|WEB|MED|CHAT)_SOURCE_\d+\]/i', '', (string) $this->content);
        foreach ((array) $this->sources as $source) {
            $url = trim((string) data_get($source, 'url', ''));
            if ($url === '' || ! preg_match('~^https?://~i', $url)) continue;
            $quoted = preg_quote($url, '~');
            $canonicalHost = preg_replace('/^www\./i', '', (string) parse_url($url, PHP_URL_HOST));
            $host = preg_quote($canonicalHost, '~');
            $text = preg_replace('~[ \t]*\(?\[(?:www\.)?'.$host.'\]\('.$quoted.'\)\)?~i', '', $text);
            $isBook = data_get($source, 'source_type') === 'knowledge_base'
                || str_starts_with((string) data_get($source, 'source_label', ''), 'KB_SOURCE_');
            $text = preg_replace('~\[([^\]]+)\]\('.$quoted.'\)~', $isBook ? '' : '$1', $text);
            $text = preg_replace('~[ \t]*\('.$quoted.'\)~', '', $text);
            $text = preg_replace('~[ \t]*'. $quoted .'(?=$|[\s).,،؛؟!])~u', '', $text);
            $text = preg_replace('~[ \t]*\((?:www\.)?'.$host.'\)~i', '', $text);
        }
        return trim(preg_replace('/[ \t]+([.,،؛؟!])/u', '$1', preg_replace('/[ \t]*\(\s*\)/u', '', $text)));
    }

    public static function attributedBookIds($sources): array
    {
        $ids = collect($sources)->pluck('knowledge_document_id')->filter()->unique()->values();
        return $ids->isEmpty() ? [] : KnowledgeDocument::whereKey($ids->all())
            ->where('is_approved', true)->where('show_attribution', true)->pluck('id')->flip()->all();
    }

    private function safeSources($request): mixed
    {
        if (! is_array($this->sources)) {
            return $this->sources;
        }

        $attributed = $request->attributes->get('chat_attributed_books') ?? self::attributedBookIds($this->sources);
        return array_values(array_filter(array_map(function ($source) use ($attributed) {
            if (! is_array($source)) {
                return $source;
            }
            if (($source['source_type'] ?? '') === 'knowledge_base' || str_starts_with($source['source_label'] ?? '', 'KB_SOURCE_')) {
                if (! isset($attributed[$source['knowledge_document_id'] ?? 0])) return null;
                return Arr::only($source, ['source_label', 'source_type', 'title', 'publisher', 'url', 'page_number']);
            }
            foreach (['url', 'file_url'] as $key) {
                $url = (string) ($source[$key] ?? '');
                if (($source['source_type'] ?? null) === 'chat_attachment'
                    || preg_match('#/storage/(?:app/)?(?:chat-attachments/|children/[^/]+/documents/)#i', rawurldecode($url))) {
                    unset($source[$key]);
                }
            }

            return $source;
        }, $this->sources), static fn ($source) => $source !== null));
    }
}
