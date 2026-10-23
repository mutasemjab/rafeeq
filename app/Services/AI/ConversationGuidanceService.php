<?php

namespace App\Services\AI;

/** Versioned conversation rules and optional case-history questions, never clinical evidence. */
class ConversationGuidanceService
{
    public const INTENTS = ['question', 'correction', 'new_concern', 'explanation', 'another_activity', 'outcome_report', 'implementation_barrier', 'stop_plan'];
    public const ANSWER_STATUSES = ['reported', 'denied', 'unknown', 'declined', 'conflicting'];
    public const DOMAINS = ['communication_language_hearing', 'interaction_play', 'learning_attention', 'movement_self_care', 'behavior_emotions', 'daily_routines', 'general_clarification'];
    private array $files = [];

    public function data(string $file): array
    {
        return $this->files[$file] ??= json_decode(file_get_contents(resource_path('ai/conversation/'.$file.'.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function version(): string
    {
        return $this->data('manifest')['version'];
    }

    public function fields(): array
    {
        return $this->data('case_study_fields')['fields'];
    }

    public function memoryKeys(): array
    {
        return array_merge(array_column($this->fields(), 'key'), ['goal.current', 'family.constraints']);
    }

    public function instructions(): string
    {
        $rules = array_merge($this->data('conversation_enhancements_ar_en')['rules'], $this->data('gateway_additions_ar_en')['rules']);
        return "\nImplemented conversation guidance ".$this->version()." (flow only, not treatment evidence):\n"
            .implode("\n", array_map(fn ($rule) => $rule['id'].': '.$rule['rule_en'], $rules));
    }

    public function context(array $context, array $state, ?string $domain, string $language): array
    {
        $known = [];
        $profile = (array) ($context['profile'] ?? []);
        foreach (['age' => 'child.age', 'birth_date' => 'child.birth_date'] as $column => $key) {
            if (isset($profile[$column])) {
                $known[$key] = ['content' => (string) $profile[$column], 'source' => 'consented_profile', 'answer_status' => 'reported'];
            }
        }
        if (isset($profile['age_months']) && ! isset($known['child.age'])) {
            $known['child.age'] = ['content' => $profile['age_months'].' months', 'source' => 'consented_profile', 'answer_status' => 'reported'];
        }
        $profileAge = $known['child.age'] ?? null;
        $profileBirth = $known['child.birth_date'] ?? null;
        $keys = $this->memoryKeys();
        foreach (array_merge((array) ($context['memories'] ?? []), (array) ($context['intake_memories'] ?? [])) as $memory) {
            $key = ChildMemoryManager::canonicalKey((string) ($memory['memory_key'] ?? ''));
            if (in_array($key, $keys, true)) {
                $known[$key] = ['content' => mb_substr((string) ($memory['content'] ?? ''), 0, 600),
                    'source' => 'caregiver_message', 'source_message_id' => $memory['source_message_id'] ?? null,
                    'reported_at' => $memory['last_confirmed_at'] ?? null,
                    'answer_status' => data_get($memory, 'metadata.answer_status', 'reported')];
            }
        }
        foreach ((array) ($state['fact_index'] ?? []) as $key => $fact) {
            if (in_array($key, $keys, true) && is_array($fact)
                && (int) ($fact['source_message_id'] ?? 0) >= (int) ($known[$key]['source_message_id'] ?? 0)) {
                $known[$key] = array_merge($fact, ['source' => 'caregiver_message']);
            }
        }
        foreach (['child.age' => $profileAge, 'child.birth_date' => $profileBirth] as $key => $value) {
            if ($value !== null && isset($profile['age_updated_at'])
                && (! isset($known[$key]['reported_at']) || strtotime($profile['age_updated_at']) >= strtotime($known[$key]['reported_at']))) {
                $known[$key] = $value;
            }
        }
        $ageMonths = $profile['age_months'] ?? (isset($profile['age']) ? (float) $profile['age'] * 12 : null);
        $adult = ($ageMonths !== null && $ageMonths >= 216) || in_array($profile['age_group'] ?? '', ['adult', 'older_adult'], true);
        $hint = mb_strtolower(implode(' ', array_filter([$domain, $state['domain'] ?? null, implode(' ', (array) ($state['exploration_domains'] ?? []))])));
        $groups = ['general'];
        foreach ([
            'communication' => ['speech', 'language', 'hearing', 'communication'],
            'social' => ['interaction', 'social', 'play', 'autism'],
            'learning' => ['learning', 'attention', 'school'],
            'movement' => ['movement', 'motor', 'self_care', 'independence'],
            'behavior' => ['behavior', 'emotion', 'tantrum'],
            'routine' => ['routine', 'sleep', 'feeding', 'eating', 'toilet'],
            'development' => ['development'],
        ] as $group => $terms) {
            foreach ($terms as $term) {
                if (str_contains($hint, $term)) { $groups[] = $group; break; }
            }
        }
        $candidates = [];
        if (! $adult) {
            foreach ($this->fields() as $field) {
                if (! $field['sensitive'] && ! isset($known[$field['key']]) && array_intersect($groups, $field['domains'])) {
                    $candidates[] = ['key' => $field['key'], 'question' => $field['question'][$language], 'section' => $field['source_section']];
                }
            }
        }
        return [
            'version' => $this->version(), 'clinical_evidence' => false,
            'case_form_applicable' => ! $adult,
            'use' => 'Optional questions for a child when relevant. Do not assume an unknown-age person is a child. Never complete the whole form or infer facts from its blank options. Sensitive family/pregnancy history is voluntary and only relevant when explicitly raised. Known unknown/declined answers are not negative findings; do not repeatedly demand them.',
            'field_keys' => $adult ? [] : array_column($this->fields(), 'key'),
            'known_answers' => $known,
            'candidate_questions' => array_slice($candidates, 0, 18),
            'family_goal' => $known['goal.current'] ?? null,
            'family_constraints' => $known['family.constraints'] ?? null,
            'domains' => self::DOMAINS,
        ];
    }

    /** Model intent cannot outrank the existing safety and evidence gates. */
    public function route(array $plan, array $safety = []): array
    {
        $intent = in_array($plan['intent'] ?? '', self::INTENTS, true) ? $plan['intent'] : 'question';
        $target = (string) ($plan['question_target'] ?? '');
        $node = match (true) {
            in_array($safety['level'] ?? '', ['emergency', 'urgent_specialist'], true) => 'N04',
            ($plan['action'] ?? '') === 'ask_clarification' && preg_match('/identity|conflict|ambiguous/i', $target) === 1 => 'N02',
            ($plan['action'] ?? '') === 'ask_clarification' && preg_match('/safety|injury|danger/i', $target) === 1 => 'N05',
            ($plan['action'] ?? '') === 'ask_clarification' => 'N08',
            ($plan['action'] ?? '') === 'refer_to_specialist' => 'N12',
            $intent === 'correction' => 'N01',
            $intent === 'explanation' => 'N07',
            $intent === 'another_activity' => 'N10',
            in_array($intent, ['outcome_report', 'implementation_barrier', 'stop_plan'], true) => 'N11',
            default => 'N09',
        };
        $definition = collect($this->data('dialogue_tree_en')['nodes'])->firstWhere('id', $node);
        return ['version' => $this->version(), 'node_id' => $node, 'intent' => $intent,
            'action' => $definition['action_en'], 'clinical_evidence' => false,
            'gateway_stage' => $node === 'N04' ? 'BASE_SAFETY' : ($node === 'N08' ? 'G03' : ($node === 'N11' ? 'G05' : 'G04'))];
    }
}
