<?php

namespace App\Services\AI;

use App\Models\ChildMemory;
use App\Models\Child;
use App\Models\Message;
use App\Models\PersonProfile;
use App\Models\PersonMemory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChildMemoryManager
{
    private const KEY_ALIASES = [
        'age' => 'child.age', 'age_years' => 'child.age', 'age_months' => 'child.age',
        'child.age_years' => 'child.age', 'child.age_months' => 'child.age',
        'profile.age' => 'child.age', 'development.age' => 'child.age',
        'birth_date' => 'child.birth_date', 'profile.birth_date' => 'child.birth_date',
        'person.age' => 'child.age', 'person.age_months' => 'child.age', 'person.age_years' => 'child.age',
        'person.birth_date' => 'child.birth_date',
        'primary_language' => 'communication.primary_language',
        'language.primary' => 'communication.primary_language',
    ];

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

        // Different chats for the same child can finish concurrently. Lock the
        // parent row so canonical updates and their history remain atomic.
        return DB::transaction(function () use ($childId, $userId, $sourceMessageId, $candidates): int {
            if (Child::query()->whereKey($childId)->where('user_id', $userId)->lockForUpdate()->first() === null) {
                return 0;
            }

            return $this->persistCandidates($childId, $userId, $sourceMessageId, $candidates);
        });
    }

    public function applyPersonCandidates(int $personId, int $userId, int $sourceMessageId, array $candidates): int
    {
        if ($candidates === []) {
            return 0;
        }

        return DB::transaction(function () use ($personId, $userId, $sourceMessageId, $candidates): int {
            $person = PersonProfile::whereKey($personId)->where('user_id', $userId)->lockForUpdate()->first();
            if (! $person || ! $person->hasProcessingConsent() || ! $person->user?->hasAiConsent()) {
                return 0;
            }

            return $this->persistCandidates($personId, $userId, $sourceMessageId, $candidates, true);
        });
    }

    private function persistCandidates(int $childId, int $userId, ?int $sourceMessageId, array $candidates, bool $person = false): int
    {
        $memoryModel = $person ? PersonMemory::class : ChildMemory::class;
        $parentKey = $person ? 'person_profile_id' : 'child_id';
        $parent = $person ? PersonProfile::find($childId) : Child::find($childId);
        $saved = 0;
        $minimumConfidence = (float) config('ai.memory_minimum_confidence', 0.78);
        $sourceMessage = $sourceMessageId === null ? null : Message::query()
            ->whereKey($sourceMessageId)->where('user_id', $userId)
            ->when($person,
                fn ($query) => $query->whereHas('conversation', fn ($conversation) => $conversation->where('person_profile_id', $childId)->where('user_id', $userId)),
                fn ($query) => $query->where('child_id', $childId))
            ->where('role', 'user')->first();
        if ($sourceMessageId !== null && $sourceMessage === null) {
            return 0;
        }

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
                || ($sourceMessage !== null && mb_stripos((string) $sourceMessage->content, $evidence) === false)
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
            if (in_array($memoryKey, ['child.age','child.birth_date'], true) && $sourceMessage !== null
                && $parent?->age_updated_at !== null && $sourceMessage->created_at->lte($parent->age_updated_at)) {
                continue;
            }
            $existing = $memoryModel::query()
                ->where($parentKey, $childId)
                ->where('user_id', $userId)
                ->whereIn('memory_key', self::aliasesFor($memoryKey))
                ->where('status', 'active')->orderByDesc('source_message_id')->latest('updated_at')
                ->first();

            // Delayed jobs must never overwrite a newer correction.
            if ($existing !== null && $sourceMessageId !== null
                && $existing->source_message_id !== null && $sourceMessageId < $existing->source_message_id) {
                continue;
            }

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
                    'source_message_id' => $existing->source_message_id,
                    'status' => 'superseded',
                ];
                $metadata['history'] = $history;
            } elseif ($existing !== null) {
                $metadata = array_merge($existing->metadata ?? [], $metadata);
            }

            $identity = [
                    $parentKey => $childId,
                    'user_id' => $userId,
                    'memory_key' => $memoryKey,
                ];
            $values = [
                    'type' => $type,
                    'title' => $title,
                    'content' => mb_substr($content, 0, 4000),
                    'status' => 'active',
                    'confidence' => $confidence,
                    'source' => $person ? 'person_conversation' : 'caregiver_conversation',
                    'source_message_id' => $sourceMessageId,
                    'last_confirmed_at' => now(),
                    'metadata' => $metadata,
                ];
            $memory = $existing ?? new $memoryModel();
            $memory->fill(array_merge($identity, $values))->save();
            $memoryModel::query()->where($parentKey, $childId)->where('user_id', $userId)
                ->whereIn('memory_key', self::aliasesFor($memoryKey))->where('id', '!=', $memory->id)
                ->where('status', 'active')->update(['status' => 'superseded']);
            $saved++;
        }

        return $saved;
    }

    private function memoryKey(string $candidateKey, string $type, string $title, string $content): string
    {
        $candidateKey = trim(mb_strtolower($candidateKey));
        if ($candidateKey !== '') {
            return self::canonicalKey($candidateKey);
        }

        $slug = Str::slug($type.' '.$title);

        return mb_substr($slug !== '' ? $slug : $type.'_'.sha1($content), 0, 160);
    }

    public static function canonicalKey(string $key): string
    {
        $key = mb_substr(preg_replace('/\s+/u', '_', trim(mb_strtolower($key))) ?? '', 0, 160);

        // Explicit field aliases only. Never infer that two clinical concerns or
        // diagnoses are the same merely because their text looks similar.
        return self::KEY_ALIASES[$key] ?? $key;
    }

    private static function aliasesFor(string $canonicalKey): array
    {
        return array_merge([$canonicalKey], array_keys(self::KEY_ALIASES, $canonicalKey, true));
    }
}
