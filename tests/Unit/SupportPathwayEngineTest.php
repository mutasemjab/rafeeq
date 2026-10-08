<?php

namespace Tests\Unit;

use App\Services\AI\SupportPathwayEngine;
use App\Services\AI\SupportPathwayRegistry;
use InvalidArgumentException;
use Tests\TestCase;

class SupportPathwayEngineTest extends TestCase
{
    public function test_every_supplied_pathway_has_bilingual_nodes_and_six_specialised_questions(): void
    {
        $registry = new SupportPathwayRegistry();
        $this->assertCount(39, $registry->package()['pathways']);
        $total = 0;
        foreach ($registry->package()['pathways'] as $pathway) {
            $this->assertCount(31, $pathway['nodes']);
            $specialised = array_filter($pathway['nodes'], fn ($node) => str_starts_with($node['local_id'], 'D'));
            $this->assertCount(6, $specialised);
            $total += count($specialised);
            foreach ($pathway['nodes'] as $node) {
                $this->assertNotEmpty($node['text']['ar']);
                $this->assertNotEmpty($node['text']['en']);
            }
            $this->assertFalse($pathway['clinical_evidence']);
        }
        $this->assertSame(234, $total);
        foreach (['gateway:G11', 'gateway:G13', 'gateway:H'] as $id) {
            $this->assertSame('internal_decision', $registry->node($id)['kind']);
        }
    }

    public function test_planner_receives_questions_without_draft_treatment_branch_text(): void
    {
        $context = $this->engine()->plannerContext([], 'autism', 'ar');
        $this->assertCount(6, $context['candidate_questions']);
        $this->assertArrayNotHasKey('source_block', $context['candidate_questions'][0]);
        $this->assertStringNotContainsString('الخطوة التالية H', json_encode($context, JSON_UNESCAPED_UNICODE));
    }

    public function test_every_route_is_available_for_each_age_and_language_without_exposing_draft_treatments(): void
    {
        $registry = new SupportPathwayRegistry();
        $engine = new SupportPathwayEngine($registry);
        foreach ($registry->pathways() as $pathway) {
            foreach ([60, 180, 480, 900] as $age) {
                foreach (['ar', 'en'] as $language) {
                    $context = $engine->plannerContext([], $pathway['id'], $language,
                        ['profile' => ['person_profile_id' => 1, 'age_months' => $age]]);
                    $this->assertCount(6, $context['candidate_questions']);
                    foreach ($context['candidate_questions'] as $question) {
                        $this->assertSame($registry->node($question['id'])['text'][$language], $question['question']);
                        $this->assertNotContains('source_block', array_keys($question));
                    }
                    $this->assertNotContains('gateway:G02', array_column($context['gateway_questions'], 'id'));
                }
            }
        }
    }

    public function test_known_age_and_person_consent_are_not_offered_as_intake_questions(): void
    {
        $context = $this->engine()->plannerContext([], 'language', 'en', ['profile' => ['person_profile_id' => 1, 'age_months' => 600]]);
        $ids = array_column($context['gateway_questions'], 'id');
        $this->assertNotContains('gateway:G01', $ids);
        $this->assertNotContains('gateway:G02', $ids);
        $this->assertNotContains('gateway:G11', $ids);
    }

    public function test_a_new_problem_takes_priority_over_old_active_routes(): void
    {
        $context = $this->engine()->plannerContext(['pathway_state' => ['active_pathways' => ['language', 'speech', 'communication', 'autism']]], 'sleep', 'en');
        $this->assertSame('sleep:D01', $context['candidate_questions'][0]['id']);
    }

    public function test_unknown_declined_and_no_remain_distinct_and_do_not_repeat(): void
    {
        $engine = $this->engine();
        $context = $engine->plannerContext([], 'language', 'en');
        $state = $engine->apply(['pathway_ids' => ['language', 'hearing'], 'node_answers' => [
            ['node_id' => 'language:D01', 'status' => 'unknown', 'value' => 'Unknown', 'evidence' => "I don't know"],
            ['node_id' => 'language:D02', 'status' => 'declined', 'value' => 'Declined', 'evidence' => 'skip this'],
            ['node_id' => 'language:D03', 'status' => 'no', 'value' => 'No', 'evidence' => 'No'],
        ]], [], "I don't know. Please skip this. No.", $context);
        $this->assertSame(['language', 'hearing'], $state['active_pathways']);
        $this->assertSame('unknown', $state['answers']['language:D01']['status']);
        $this->assertSame('declined', $state['answers']['language:D02']['status']);
        $context = $engine->plannerContext(['pathway_state' => $state], 'language', 'en');
        $this->assertNotContains('language:D01', array_column($context['candidate_questions'], 'id'));
        $this->assertNotContains('language:D02', array_column($context['candidate_questions'], 'id'));
    }

    /** @dataProvider invalidSelections */
    public function test_invalid_or_unsupported_model_selection_is_rejected(array $selection): void
    {
        $engine = $this->engine();
        $this->expectException(InvalidArgumentException::class);
        $engine->apply($selection, [], 'No concerns reported.', $engine->plannerContext([], 'language', 'en'));
    }

    public static function invalidSelections(): array
    {
        return [
            [['pathway_ids' => ['invented_diagnosis']]],
            [['question_node_id' => 'gateway:G11']],
            [['question_node_id' => 'gateway:H']],
            [['question_node_id' => 'psychosis:D01']],
            [['pathway_ids' => ['language'], 'node_answers' => [['node_id' => 'language:D01', 'status' => 'yes', 'value' => 'Yes', 'evidence' => 'Never said this']]]],
            [['pathway_ids' => ['language'], 'node_answers' => [['node_id' => 'language:H', 'status' => 'yes', 'value' => 'Yes', 'evidence' => 'No']]]],
        ];
    }

    public function test_correction_supersedes_an_answer_and_invalidates_derived_decisions(): void
    {
        $engine = $this->engine();
        $previous = ['pathway_state' => ['active_pathways' => ['language'], 'answers' => [
            'language:D01' => ['status' => 'no', 'value' => 'No', 'source_message_id' => 8],
        ]]];
        $context = $engine->plannerContext($previous, 'language', 'en');
        $state = $engine->apply(['node_answers' => [['node_id' => 'language:D01', 'status' => 'reported', 'value' => 'Needs help', 'evidence' => 'needs help']]],
            $previous, 'Actually needs help.', $context);
        $this->assertSame('reported', $state['answers']['language:D01']['status']);
        $this->assertTrue($state['decisions_invalidated']);
        $this->assertSame(8, $state['corrections'][0]['previous']['source_message_id']);
    }

    private function engine(): SupportPathwayEngine
    {
        return new SupportPathwayEngine(new SupportPathwayRegistry());
    }
}
