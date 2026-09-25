<?php

namespace App\Services\AI;

use App\Models\ChildMemory;
use Illuminate\Support\Str;

class ChildMemoryManager
{
    private const ALLOWED_TYPES = [
        'diagnosis',
        'development',
        'behavior',
        'school',
        'therapy',
        'medical',
        'communication',
        'language',
        'social',
        'learning',
        'independence',
        'goal',
        'preference',
        'general',
    ];

    /**
     * Persist only durable facts explicitly reported by the caregiver.
     */
    public function applyCandidates(
        ?int $childId,
        int $userId,
        ?int $sourceMessageId,
        array $candidates
    ): int {
        if ($childId === null || $candidates === []) {
            return 0;
        }

        $saved = 0;
        $minimumConfidence = (float) config('ai.memory_minimum_confidence', 0.78);

        foreach (array_slice($candidates, 0, 8) as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $content = trim((string) ($candidate['content'] ?? ''));
            $evidence = trim((string) ($candidate['evidence'] ?? ''));
            $confidence = max(0.0, min(1.0, (float) ($candidate['confidence'] ?? 0)));
            $factStatus = (string) ($candidate['fact_status'] ?? 'reported_concern');

            if (
                $content === ''
                || $evidence === ''
                || $confidence < $minimumConfidence
                || ! in_array($factStatus, ['confirmed_by_caregiver', 'reported_concern', 'goal', 'preference'], true)
            ) {
                continue;
            }

            $type = (string) ($candidate['type'] ?? 'general');
            if (! in_array($type, self::ALLOWED_TYPES, true)) {
                $type = 'general';
            }

            $title = mb_substr(trim((string) ($candidate['title'] ?? $type)), 0, 160);
            $memoryKey = $this->memoryKey((string) ($candidate['key'] ?? ''), $type, $title, $content);
            $existing = ChildMemory::query()
                ->where('child_id', $childId)
                ->where('user_id', $userId)
                ->where('memory_key', $memoryKey)
                ->first();

            $metadata = [
                'fact_status' => $factStatus,
                'evidence' => mb_substr($evidence, 0, 500),
                'last_source_message_id' => $sourceMessageId,
            ];

            if ($existing !== null && trim((string) $existing->content) !== $content) {
                $history = collect(data_get($existing->metadata, 'history', []))
                    ->filter(fn ($item): bool => is_array($item))
                    ->take(-9)
                    ->values()
                    ->all();
                $history[] = [
                    'content' => $existing->content,
                    'confidence' => $existing->confidence,
                    'changed_at' => now()->toISOString(),
                ];
                $metadata['history'] = $history;
            } elseif ($existing !== null) {
                $metadata = array_merge($existing->metadata ?? [], $metadata);
            }

            ChildMemory::query()->updateOrCreate(
                [
                    'child_id' => $childId,
                    'user_id' => $userId,
                    'memory_key' => $memoryKey,
                ],
                [
                    'type' => $type,
                    'title' => $title,
                    'content' => mb_substr($content, 0, 4000),
                    'status' => 'active',
                    'confidence' => $confidence,
                    'source' => 'caregiver_conversation',
                    'source_message_id' => $sourceMessageId,
                    'last_confirmed_at' => now(),
                    'metadata' => $metadata,
                ]
            );
            $saved++;
        }

        return $saved;
    }

    private function memoryKey(string $candidateKey, string $type, string $title, string $content): string
    {
        $candidateKey = trim(mb_strtolower($candidateKey));
        if ($candidateKey !== '') {
            return mb_substr(preg_replace('/\s+/u', '_', $candidateKey) ?? $candidateKey, 0, 160);
        }

        $slug = Str::slug($type.' '.$title);

        return mb_substr($slug !== '' ? $slug : $type.'_'.sha1($content), 0, 160);
    }
}
