<?php

namespace App\Services\AI;

use InvalidArgumentException;

class SupportPathwayEngine
{
    public function __construct(private SupportPathwayRegistry $registry) {}

    public function plannerContext(array $state, ?string $domain, string $language, array $caseContext = []): array
    {
        $active = (array) data_get($state, 'pathway_state.active_pathways', []);
        $aliases = [
            'speech' => ['speech', 'language', 'hearing'], 'communication' => ['communication', 'language', 'hearing'],
            'expressive_language' => ['language', 'hearing'], 'behavior' => ['disruptive', 'autism'],
            'development' => ['neurodevelopment', 'other_neuro'], 'feeding' => ['eating'],
            'mental_health' => ['depression', 'anxiety'], 'learning' => ['learning', 'adhd'],
            'down_syndrome' => ['down_syndrome_support'], 'cognition' => ['cognition'],
        ];
        $hint = mb_strtolower((string) $domain);
        $hintIds = $this->registry->pathway($hint) !== null ? [$hint] : ($aliases[$hint] ?? []);
        $candidateRoutes = array_slice(array_values(array_unique(array_merge($hintIds, $active))), 0, 4);
        $answers = (array) data_get($state, 'pathway_state.answers', []);
        $questions = [];
        foreach ($candidateRoutes as $route) {
            foreach ($this->registry->pathway($route)['nodes'] ?? [] as $node) {
                if (str_starts_with($node['local_id'], 'D') && ! isset($answers[$node['id']])) {
                    $questions[] = ['id' => $node['id'], 'question' => $node['text'][$language] ?? $node['text']['en']];
                }
            }
        }
        $profile = (array) ($caseContext['profile'] ?? []);
        $knownGateway = [];
        if (isset($profile['age_months']) || isset($profile['birth_date']) || isset($profile['age'])) {
            $knownGateway[] = 'gateway:G02';
        }
        if (isset($profile['person_profile_id'])) {
            $knownGateway[] = 'gateway:G01';
        }

        return [
            'version' => $this->registry->package()['version'],
            'purpose' => 'Draft exploration routes and optional questions, not diagnoses, screening scores or treatment evidence.',
            'catalogue' => $this->registry->catalogue(), 'active_pathways' => $active,
            'pending_question_id' => data_get($state, 'pathway_state.pending_question_id'),
            'answers' => $answers, 'candidate_questions' => $questions,
            'gateway_questions' => collect($this->registry->package()['gateway']['nodes'])
                ->filter(fn ($node): bool => $node['kind'] === 'question' && ! isset($answers[$node['id']]) && ! in_array($node['id'], $knownGateway, true))
                ->map(fn ($node): array => ['id' => $node['id'], 'question' => $node['text'][$language] ?? $node['text']['en']])
                ->values()->all(),
        ];
    }

    /** Validate source-bound observations; no yes/no branch executes a therapy. */
    public function apply(array $selection, array $previous, string $message, array $context): array
    {
        $state = (array) ($previous['pathway_state'] ?? []);
        $state['decisions_invalidated'] = false;
        if (! is_array($selection['pathway_ids'] ?? []) || ! is_array($selection['node_answers'] ?? [])
            || count($selection['pathway_ids'] ?? []) > 4 || count($selection['node_answers'] ?? []) > 6) {
            throw new InvalidArgumentException('Invalid bounded pathway selection.');
        }
        $active = (array) ($state['active_pathways'] ?? []);
        $selected = [];
        foreach (array_slice((array) ($selection['pathway_ids'] ?? []), 0, 4) as $id) {
            if (! is_string($id) || $this->registry->pathway($id) === null) {
                throw new InvalidArgumentException('Unknown support pathway ID.');
            }
            $selected[] = $id;
        }
        $active = array_slice(array_values(array_unique(array_merge($selected, $active))), 0, 8);
        $answers = (array) ($state['answers'] ?? []);
        $changes = (array) ($state['corrections'] ?? []);
        foreach (array_slice((array) ($selection['node_answers'] ?? []), 0, 6) as $answer) {
            $id = $answer['node_id'] ?? null;
            $node = is_string($id) ? $this->registry->node($id) : null;
            $status = $answer['status'] ?? null;
            if (! is_array($answer) || ! is_string($answer['evidence'] ?? null) || ! is_string($answer['value'] ?? null)) {
                throw new InvalidArgumentException('Invalid pathway observation fields.');
            }
            $evidence = trim($answer['evidence']);
            [$route] = explode(':', (string) $id);
            if ($node === null || $node['kind'] !== 'question'
                || ! in_array($status, ['yes', 'no', 'reported', 'unknown', 'declined', 'conflicting'], true)
                || ($route !== 'gateway' && ! in_array($route, $active, true))
                || $evidence === '' || mb_stripos($message, $evidence) === false) {
                throw new InvalidArgumentException('Unsupported pathway observation or evidence.');
            }
            $updated = ['status' => $status, 'value' => mb_substr(trim((string) ($answer['value'] ?? '')), 0, 500),
                'evidence' => mb_substr($evidence, 0, 500), 'source' => 'latest_user_message'];
            if (isset($answers[$id]) && ($answers[$id]['status'] !== $status || $answers[$id]['value'] !== $updated['value'])) {
                $changes[] = ['node_id' => $id, 'previous' => $answers[$id]];
                // Derived routing/support decisions must be recomputed from the new snapshot.
                $state['decisions_invalidated'] = true;
            }
            $answers[$id] = $updated;
        }
        $questionId = $selection['question_node_id'] ?? null;
        if ($questionId !== null) {
            $candidates = array_merge($context['candidate_questions'] ?? [], $context['gateway_questions'] ?? []);
            if (! in_array($questionId, array_column($candidates, 'id'), true) || isset($answers[$questionId])) {
                throw new InvalidArgumentException('Question is not an unanswered eligible pathway node.');
            }
        }

        return array_merge($state, [
            'version' => $this->registry->package()['version'], 'review_status' => 'draft',
            'active_pathways' => $active, 'answers' => $answers,
            'corrections' => array_slice($changes, -12), 'pending_question_id' => $questionId,
            'diagnostic_tool' => false,
        ]);
    }
}
