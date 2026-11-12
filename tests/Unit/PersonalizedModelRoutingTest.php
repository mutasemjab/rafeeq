<?php

namespace Tests\Unit;

use App\Services\AI\AnswerModelPolicy;
use App\Services\AI\AnswerQualityService;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\AI\KnowledgeEvidenceService;
use App\Services\AI\PersonalizedModelRouter;
use Mockery;
use Tests\TestCase;

class PersonalizedModelRoutingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.adaptive_personalized_routing' => true, 'ai.balanced_personalized_model' => 'balanced',
            'ai.answer_model' => 'primary', 'ai.personalized_knowledge_coverage_model' => 'primary',
            'ai.answer_quality_model' => 'primary']);
    }

    private function plan(): array
    {
        return ['risk_level' => 'low', 'confidence' => 0.98, 'response_mode' => 'fast', 'case_specific' => true,
            'action' => 'answer', 'critical_gap' => false,
            'knowledge_grounding' => ['mode' => 'internal', 'supporting_labels' => ['KB_SOURCE_1']]];
    }

    private function approve(): array
    {
        return ['action' => 'approve', 'issues' => [], 'scores' => array_fill_keys(
            ['specificity', 'grounding', 'practicality', 'professional_tone', 'calibration'], 1)];
    }

    private function sources(): array
    {
        return [['source_label' => 'KB_SOURCE_1', 'content' => 'Use one short familiar instruction with a gesture. Do not assume an unreported prerequisite.']];
    }

    private function coverage(bool $sufficient = true): array
    {
        return ['sufficient' => $sufficient, 'supporting_quotes' => [['source_label' => 'KB_SOURCE_1', 'excerpt_id' => 1]],
            'missing_information' => $sufficient ? [] : ['An applicable explanation is missing.'],
            'search_queries' => $sufficient ? [] : ['receptive language familiar instructions'], 'clarification_question' => null];
    }

    /** @dataProvider carefulPlans */
    public function test_uncertain_or_complex_cases_do_not_take_the_balanced_route(array $changes, string $question = 'Help with instructions.'): void
    {
        $plan = array_replace($this->plan(), $changes);
        $this->assertFalse(app(PersonalizedModelRouter::class)->eligible($plan, $question));
    }

    public static function carefulPlans(): array
    {
        return [[['risk_level' => 'moderate']], [['risk_level' => 'high']], [['risk_level' => null]],
            [['confidence' => null]], [['confidence' => 0.8]], [['response_mode' => 'deep']],
            [['critical_gap' => true]], [['after_referral' => true]], [['action' => 'ask_clarification']],
            [['case_specific' => false]], [['cost_routing_force_primary' => true]],
            [['previous_evidence_gaps' => ['Unresolved applicability']]],
            [['knowledge_grounding' => ['mode' => 'internal', 'coverage_escalated' => true]]],
            [['pathway_state' => ['active_pathways' => ['psychosis']]]], [[], 'هل أغير جرعة الدواء؟']];
    }

    public function test_clear_grounded_answers_use_balanced_but_rollback_and_feedback_preserve_primary(): void
    {
        $policy = app(AnswerModelPolicy::class);
        $options = $policy->options($this->plan(), 'Help with instructions.');
        $this->assertSame('balanced', $options['model']);
        $this->assertSame('low', $options['reasoning_effort']);
        $this->assertGreaterThanOrEqual(1600, $options['max_completion_tokens']);
        $this->assertFalse(app(PersonalizedModelRouter::class)->eligible($this->plan(), 'Help', ['reply_feedback' => ['reason' => 'misunderstood']]));
        config(['ai.adaptive_personalized_routing' => false]);
        $this->assertSame('primary', $policy->options($this->plan(), 'Help')['model']);
    }

    public function test_first_pass_reads_all_passages_and_an_unresolved_second_pass_uses_primary(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->withArgs(function ($messages, $schema, $options) {
            $input = json_decode($messages[1]['content'], true);
            return $options['model'] === 'balanced' && count($input['passages']) === 1
                && str_contains(implode(' ', $input['passages']['KB_SOURCE_1']['excerpts']), 'Do not assume an unreported prerequisite.');
        })->andReturn($this->coverage(false));
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($m,$s,$o) =>
            $o['model'] === 'primary' && $o['schema_name'] === 'rafeeq_knowledge_coverage_escalation')
            ->andReturn($this->coverage());
        $reader = new KnowledgeEvidenceService($llm);
        $first = $reader->assess('Help with instructions.', $this->plan(), [], $this->sources());
        $this->assertFalse($first['sufficient']);
        $second = $reader->assess('Help with instructions.', $this->plan() + ['previous_evidence_gaps' => $first['missing_information']], [], $this->sources());
        $this->assertTrue($second['model_escalated']);
        $this->assertTrue($second['sufficient']);
    }

    public function test_invalid_coverage_cannot_skip_to_web_or_escape_primary_validation(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $bad = $this->coverage(); $bad['supporting_quotes'][0]['excerpt_id'] = 99;
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($m,$s,$o) => $o['model'] === 'balanced')->andReturn($bad);
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($m,$s,$o) => $o['model'] === 'primary')->andReturn($bad);
        $this->expectException(\RuntimeException::class);
        (new KnowledgeEvidenceService($llm))->assess('Help', $this->plan(), [], $this->sources());
    }

    public function test_balanced_draft_keeps_primary_review_and_independent_primary_verification(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->withArgs(function ($m, $s, $o) {
            $input = json_decode($m[1]['content'], true);
            return $o['schema_name'] === 'rafeeq_answer_quality' && $o['model'] === 'primary'
                && $input['draft_answer'] === 'Original draft. [KB_SOURCE_1]' && count($m) === 2;
        })->andReturn(['action' => 'revise', 'revised_answer' => 'Use a short instruction. [KB_SOURCE_1]']);
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($m, $s, $o) =>
            $o['schema_name'] === 'rafeeq_answer_quality_verification' && $o['model'] === 'primary'
            && $s['properties']['action']['enum'] === ['approve', 'reject'])
            ->andReturn(['action' => 'approve']);
        config(['ai.answer_quality_verifier_model' => 'primary']);
        $result = (new AnswerQualityService($llm))->review('Help', 'Original draft. [KB_SOURCE_1]',
            $this->plan(), [], '[KB_SOURCE_1] Use a short instruction.', 'en');
        $this->assertTrue($result['passed']);
        $this->assertTrue($result['revision_verified']);
        $this->assertSame('primary', $result['model']);
    }

    public function test_primary_review_failure_never_approves_the_balanced_draft(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($m, $s, $o) => $o['model'] === 'primary')
            ->andThrow(new \RuntimeException('Unavailable'));
        $result = (new AnswerQualityService($llm))->review('Help', 'Draft. [KB_SOURCE_1]',
            $this->plan(), [], '[KB_SOURCE_1] Evidence.', 'en');
        $this->assertFalse($result['passed']);
    }
}
