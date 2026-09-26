<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\LlmProviderInterface;

class FakeLlmProvider implements LlmProviderInterface
{
    /**
     * Return a fake chat response for testing.
     */
    public function chat(array $messages, array $options = []): string
    {
        return 'This is a fake AI response for testing.';
    }

    public function answer(array $messages, array $options = []): array
    {
        return [
            'content' => $this->chat($messages, $options),
            'sources' => [],
            'model' => 'fake',
            'used_web_search' => false,
            'usage' => [],
        ];
    }

    /**
     * Return a fake JSON response for testing.
     * Returns ['memories' => []] or an empty array depending on the schema hint.
     */
    public function chatJson(array $messages, array $schema = [], array $options = []): array
    {
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : $schema;

        if (isset($properties['level']) || $this->messagesHintSafetyTriage($messages)) {
            return [
                'level' => 'routine',
                'reason_code' => 'fake_routine',
                'reason' => 'No safety concern in the fake provider.',
                'confidence' => 1.0,
                'flags' => [],
            ];
        }

        if (isset($properties['action']) || $this->messagesHintTurnPlanner($messages)) {
            return [
                'action' => 'answer',
                'domain' => 'test',
                'case_specific' => false,
                'information_sufficient' => true,
                'reason' => 'Enough information in the fake provider.',
                'question' => null,
                'known_facts' => [],
                'decision_to_make' => null,
                'question_target' => null,
                'question_anchor' => null,
                'expected_answer_use' => null,
                'missing_fields' => [],
                'search_queries' => [],
                'follow_up_needed' => false,
                'risk_level' => 'low',
                'evidence_required' => true,
                'web_search_needed' => false,
                'memory_candidates' => [],
                'confidence' => 1.0,
            ];
        }

        if (isset($properties['wait_for_observation']) || $this->messagesHintFollowUp($messages)) {
            return [
                'question' => null,
                'purpose' => null,
                'wait_for_observation' => false,
                'anchor' => null,
                'decision_impact' => null,
            ];
        }

        if (isset($properties['allowed']) || $this->messagesHintDomainGuard($messages)) {
            return [
                'allowed' => true,
                'confidence' => 1.0,
                'category' => 'test',
                'reason' => 'Allowed by the fake test provider.',
                'search_queries' => [],
            ];
        }

        // If the schema or messages hint at a memories structure, return the appropriate shape.
        if (isset($schema['memories']) || $this->messagesHintMemories($messages)) {
            return ['memories' => []];
        }

        return [];
    }

    /**
     * Return a fake embedding vector for testing.
     * Produces a vector of the configured dimension filled with 0.01.
     */
    public function embedding(string $text): array
    {
        $dimensions = (int) config('ai.embedding_dimensions', 1536);

        return array_fill(0, $dimensions, 0.01);
    }

    public function embeddingMany(array $texts): array
    {
        return array_map(fn ($text): array => $this->embedding((string) $text), $texts);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Detect whether any message content references memory extraction.
     */
    private function messagesHintMemories(array $messages): bool
    {
        foreach ($messages as $message) {
            $content = $message['content'] ?? '';
            if (stripos($content, 'memor') !== false) {
                return true;
            }
        }

        return false;
    }

    private function messagesHintDomainGuard(array $messages): bool
    {
        foreach ($messages as $message) {
            if (stripos((string) ($message['content'] ?? ''), 'subject classifier') !== false) {
                return true;
            }
        }

        return false;
    }

    private function messagesHintSafetyTriage(array $messages): bool
    {
        foreach ($messages as $message) {
            if (stripos((string) ($message['content'] ?? ''), 'safety triage classifier') !== false) {
                return true;
            }
        }

        return false;
    }

    private function messagesHintTurnPlanner(array $messages): bool
    {
        foreach ($messages as $message) {
            if (stripos((string) ($message['content'] ?? ''), 'plan the next turn') !== false) {
                return true;
            }
        }

        return false;
    }

    private function messagesHintFollowUp(array $messages): bool
    {
        foreach ($messages as $message) {
            if (stripos((string) ($message['content'] ?? ''), 'single best next conversational question') !== false) {
                return true;
            }
        }

        return false;
    }
}
