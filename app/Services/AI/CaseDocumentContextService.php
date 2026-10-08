<?php

namespace App\Services\AI;

use App\Models\ChatAttachment;
use App\Models\Child;
use App\Models\ChildDocument;
use App\Models\Conversation;
use App\Models\User;

class CaseDocumentContextService
{
    private const MAX_FILES = 5;

    private const MAX_EXCERPT_CHARS = 1200;

    private const MAX_GROUP_CHARS = 6000;

    /**
     * Read a small, local preview before planning. Document text is reference
     * data, never an instruction or independently verified clinical evidence.
     * Extraction and embeddings must not run on this latency-sensitive path.
     */
    public function build(int $userId, Conversation $conversation, ?int $childId = null): array
    {
        $context = [
            'trust' => 'user_supplied_reference_data',
            'attachments' => [],
            'child_documents' => [],
        ];
        if (! User::query()->whereKey($userId)->whereNotNull('ai_consent_accepted_at')->exists()) {
            return $context;
        }
        if (! app(PersonContextService::class)->canProcess($conversation, $userId)) {
            return $context;
        }
        $childId = $childId ?? $conversation->child_id;

        if (! Conversation::query()
            ->whereKey($conversation->id)
            ->where('user_id', $userId)
            ->where('child_id', $childId)
            ->exists()) {
            return $context;
        }

        if ($childId !== null && ! Child::query()
            ->whereKey($childId)
            ->where('user_id', $userId)
            ->exists()) {
            return $context;
        }

        $attachments = ChatAttachment::query()
            ->where('user_id', $userId)
            ->where('conversation_id', $conversation->id)
            ->where('child_id', $childId)
            ->latest('id')
            ->limit(self::MAX_FILES)
            ->get();
        $remaining = self::MAX_GROUP_CHARS;

        foreach ($attachments as $attachment) {
            $excerpts = [];
            $budgetExhausted = $attachment->status === 'processed' && $remaining <= 0;
            if ($attachment->status === 'processed' && $remaining > 0) {
                $chunks = $attachment->chunks()
                    ->where('user_id', $userId)
                    ->where('conversation_id', $conversation->id)
                    ->where('child_id', $childId);
                // Include the beginning and conclusion of a report, even when
                // its conclusions are not in the first embedding chunk.
                $first = (clone $chunks)->orderBy('chunk_index')->first();
                $last = (clone $chunks)->orderByDesc('chunk_index')->first();
                foreach (collect([$first, $last])->filter()->unique('id') as $chunk) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $text = trim((string) $chunk->content);
                    if ($text === '') {
                        continue;
                    }
                    $content = mb_substr($text, 0, min(self::MAX_EXCERPT_CHARS, $remaining));
                    $remaining -= mb_strlen($content);
                    $excerpts[] = [
                        'chunk_index' => (int) $chunk->chunk_index,
                        'content' => $content,
                        'truncated' => mb_strlen($content) < mb_strlen($text),
                    ];
                }
            }

            $context['attachments'][] = [
                'id' => (int) $attachment->id,
                'name' => mb_substr((string) $attachment->original_name, 0, 200),
                'status' => $attachment->status,
                'content_available' => $excerpts !== [],
                'content_status' => $excerpts !== [] ? 'partial_preview'
                    : ($budgetExhausted ? 'context_budget_exhausted' : $this->unavailableStatus($attachment->status)),
                'excerpts' => $excerpts,
            ];
        }

        if ($childId === null) {
            return $context;
        }

        $documents = ChildDocument::query()
            ->where('user_id', $userId)
            ->where('child_id', $childId)
            ->latest('id')
            ->limit(self::MAX_FILES)
            ->get();
        $remaining = self::MAX_GROUP_CHARS;
        foreach ($documents as $document) {
            $metadata = is_array($document->metadata) ? $document->metadata : [];
            $text = $document->status === 'processed' && is_string($metadata['extracted_text'] ?? null)
                ? trim($metadata['extracted_text'])
                : '';
            $excerpts = [];
            if ($text !== '' && $remaining > 0) {
                $limit = min(2 * self::MAX_EXCERPT_CHARS, $remaining);
                if (mb_strlen($text) <= $limit) {
                    $excerpts[] = ['position' => 'available_text', 'content' => $text];
                    $remaining -= mb_strlen($text);
                } else {
                    $startLength = (int) ceil($limit / 2);
                    $endLength = $limit - $startLength;
                    $excerpts[] = ['position' => 'start', 'content' => mb_substr($text, 0, $startLength)];
                    if ($endLength > 0) {
                        $excerpts[] = ['position' => 'end', 'content' => mb_substr($text, -$endLength)];
                    }
                    $remaining -= $limit;
                }
            }

            $context['child_documents'][] = [
                'id' => (int) $document->id,
                'name' => mb_substr((string) ($document->title ?: $document->original_name), 0, 200),
                'category' => mb_substr((string) $document->category, 0, 100),
                'status' => $document->status,
                'content_available' => $excerpts !== [],
                'content_status' => $excerpts !== [] ? 'partial_preview'
                    : ($text !== '' ? 'context_budget_exhausted' : $this->unavailableStatus($document->status)),
                'excerpts' => $excerpts,
            ];
        }

        return $context;
    }

    private function unavailableStatus(string $status): string
    {
        return match ($status) {
            'uploaded', 'processing' => 'not_yet_readable',
            'failed' => 'processing_failed',
            default => 'no_readable_preview',
        };
    }
}
