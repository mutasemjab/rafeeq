<?php

namespace Tests\Unit;

use App\Services\AI\AnswerQualityService;
use App\Services\AI\Contracts\LlmProviderInterface;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class AnswerQualityServiceTest extends TestCase
{
    public function test_personalized_fast_review_reasons_and_verifies_revisions_without_a_second_rewrite(): void
    {
        config(['ai.fast_review_reasoning_effort' => 'none']);
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($m, $s, $o) =>
            $o['schema_name'] === 'rafeeq_answer_quality' && $o['reasoning_effort'] === 'low' && !($o['web_search'] ?? false))
            ->andReturn(['action' => 'revise', 'revised_answer' => 'Use a short instruction. [KB_SOURCE_1]']);
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($m, $s, $o) =>
            $o['schema_name'] === 'rafeeq_answer_quality_verification' && $o['reasoning_effort'] === 'low'
            && $s['properties']['action']['enum'] === ['approve', 'reject'])
            ->andReturn(['action' => 'approve']);
        $result = (new AnswerQualityService($llm))->review('Help my child understand.', 'A longer unsupported plan. [KB_SOURCE_1]',
            ['response_mode' => 'fast', 'risk_level' => 'low', 'case_specific' => true,
                'knowledge_grounding' => ['mode' => 'internal']], [], '[KB_SOURCE_1] Use short instructions.', 'en');
        $this->assertTrue($result['passed']);
        $this->assertTrue($result['revision_verified']);
    }

    /** @dataProvider datedEvidenceDrafts */
    public function test_precise_web_dates_receive_reasoning_review_without_slowing_year_only_or_book_answers(string $draft, string $mode, string $effort): void
    {
        config(['ai.fast_review_reasoning_effort' => 'none']);
        $web = $mode === 'web_gap_fill';
        $label = $web ? 'WEB_SOURCE_1' : 'KB_SOURCE_1';
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($messages, $schema, $options) =>
            $options['reasoning_effort'] === $effort && ($options['web_search'] ?? false) === $web)
            ->andReturn(['action' => 'approve', '_provider_web_search_verified' => $web]);
        $result = (new AnswerQualityService($llm))->review('What is the figure and data year?', $draft.' ['.$label.']',
            ['response_mode' => 'fast', 'risk_level' => 'low', 'knowledge_grounding' => ['mode' => $mode]],
            [], '['.$label.'] The supplied evidence.', 'en');
        $this->assertTrue($result['passed']);
    }

    public static function datedEvidenceDrafts(): array
    {
        return [
            ['Published **27 مايو 2025**.', 'web_gap_fill', 'low'],
            ['Published May 27, 2025.', 'web_gap_fill', 'low'],
            ['Published 2025-05-27.', 'web_gap_fill', 'low'],
            ['Published 27/05/2025.', 'web_gap_fill', 'low'],
            ['Published in 2025, using data from 2022.', 'web_gap_fill', 'none'],
            ['Published 27 May 2025.', 'internal', 'none'],
        ];
    }

    public function test_web_gap_review_retrieves_cited_pages_before_approving(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($messages, $schema, $options) =>
            ($options['web_search'] ?? false) === true && str_contains($messages[0]['content'], 'WEB EVIDENCE VERIFICATION'))
            ->andReturn(['action' => 'approve', '_provider_web_search_verified' => true,
                '_provider_web_sources' => [['url' => 'https://www.cdc.gov/report']]]);
        $result = (new AnswerQualityService($llm))->review('What is the current figure?',
            'A verified figure. [WEB_SOURCE_1]', ['knowledge_grounding' => ['mode' => 'web_gap_fill']],
            [], '[WEB_SOURCE_1] URL: https://www.cdc.gov/report', 'en');
        $this->assertTrue($result['passed']);
        $this->assertTrue($result['web_evidence_verified']);
    }

    public function test_web_review_without_provider_confirmation_is_not_approved(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andReturn(['action' => 'approve']);
        $result = (new AnswerQualityService($llm))->review('Current figure?', 'An unverified figure. [WEB_SOURCE_1]',
            ['knowledge_grounding' => ['mode' => 'web_gap_fill'], 'evidence_required' => true],
            [], '[WEB_SOURCE_1] URL: https://www.cdc.gov/report', 'en');
        $this->assertFalse($result['passed']);
        $this->assertFalse($result['reviewed']);
    }

    public function test_library_answer_review_does_not_enable_web_access(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($messages, $schema, $options) => ! ($options['web_search'] ?? false))
            ->andReturn(['action' => 'approve']);
        $result = (new AnswerQualityService($llm))->review('What is communication?', 'A shared message. [KB_SOURCE_1]',
            ['knowledge_grounding' => ['mode' => 'internal']], [], '[KB_SOURCE_1] A shared message.', 'en');
        $this->assertTrue($result['passed']);
        $this->assertFalse($result['web_evidence_verified']);
    }

    public function test_a_book_citation_does_not_bypass_a_semantic_rejection(): void
    {
        config(['ai.fast_general_citation_check_only' => true]); // Even a stale deployment setting cannot bypass review.
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andReturn(['action' => 'reject', 'issues' => ['The book does not support the extra claim.']]);
        $result = (new AnswerQualityService($llm))->review('What is language?', 'Invented claim. [KB_SOURCE_1]',
            ['response_mode' => 'fast', 'risk_level' => 'low', 'general_education' => true, 'case_specific' => false,
                'knowledge_grounding' => ['mode' => 'internal']], [], '[KB_SOURCE_1] Language is a shared code.', 'en');
        $this->assertFalse($result['passed']);
        $this->assertTrue($result['reviewed']);
    }

    public function test_a_book_answer_must_retain_valid_library_citations_after_review(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->twice()->andReturn(
            ['action' => 'approve'], ['action' => 'revise', 'revised_answer' => 'A shared code. [WEB_SOURCE_1]']
        );
        $result = (new AnswerQualityService($llm))->review('What is language?', 'A shared code. [WEB_SOURCE_1]',
            ['knowledge_grounding' => ['mode' => 'internal']], [], '[KB_SOURCE_1] Language is a shared code.', 'en');
        $this->assertFalse($result['passed']);
        $this->assertSame('reject', $result['action']);
    }

    public function test_fast_general_fact_also_receives_semantic_review(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andReturn(['action' => 'approve']);
        $result = (new AnswerQualityService($llm))->review('What is language?', 'A shared code. [KB_SOURCE_1]',
            ['response_mode' => 'fast', 'risk_level' => 'low', 'general_education' => true, 'case_specific' => false],
            [], '[KB_SOURCE_1] Language is a shared code.', 'en');
        $this->assertTrue($result['passed']);
        $this->assertTrue($result['reviewed']);
        $this->assertArrayNotHasKey('review_type', $result);
    }

    /** @dataProvider fullReviewPlans */
    public function test_fast_mode_keeps_independent_review_for_personalized_or_risky_answers(array $overrides): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andReturn(['action' => 'approve']);
        $result = (new AnswerQualityService($llm))->review('Help with the next step.', 'A supported step. [KB_SOURCE_1]',
            array_merge(['response_mode' => 'fast', 'risk_level' => 'low', 'general_education' => true, 'case_specific' => false], $overrides),
            [], '[KB_SOURCE_1] Evidence.', 'en');
        $this->assertTrue($result['reviewed']);
    }

    public static function fullReviewPlans(): array
    {
        return [[['case_specific' => true]], [['risk_level' => 'high']], [['response_mode' => 'deep']], [['general_education' => false]], [['web_search_needed' => true]]];
    }

    public function test_unknown_citation_in_a_fast_general_answer_requires_repair_review(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andReturn(['action' => 'reject']);
        $result = (new AnswerQualityService($llm))->review('What is language?', 'Unsupported. [KB_SOURCE_99]',
            ['response_mode' => 'fast', 'risk_level' => 'low', 'general_education' => true, 'case_specific' => false],
            [], '[KB_SOURCE_1] Real evidence.', 'en');
        $this->assertFalse($result['passed']);
    }
    public function test_follow_up_is_returned_in_the_same_quality_review_request(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $question = ['question' => 'Did a word appear spontaneously?', 'purpose' => 'communication_mode',
            'wait_for_observation' => false, 'anchor' => 'new communication attempts', 'decision_impact' => 'Choose the next appropriate observation.'];
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($messages, $schema): bool => isset($schema['properties']['follow_up']))
            ->andReturn(['action' => 'approve', 'follow_up' => $question]);
        $result = (new AnswerQualityService($llm))->review('More attempts.', 'Build on those attempts.', ['evidence_required'=>false], [], '', 'en');
        $this->assertTrue($result['follow_up_reviewed']);
        $this->assertSame($question, $result['follow_up']);
    }
    public function test_unavailable_review_is_not_reported_as_approved_or_delivered_for_clinical_advice(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andThrow(new \RuntimeException('Provider unavailable'));
        $result = (new AnswerQualityService($llm))->review('Help.', 'Draft.', ['evidence_required' => true], [], '', 'en');
        $this->assertFalse($result['passed']);
        $this->assertFalse($result['reviewed']);
        $this->assertSame('unreviewed', $result['action']);
        $this->assertSame('review_unavailable', $result['reason']);
    }

    public function test_nonclinical_support_can_be_delivered_without_falsely_claiming_review(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andThrow(new \RuntimeException('Provider unavailable'));
        $result = (new AnswerQualityService($llm))->review('Thanks.', 'You are welcome.', ['evidence_required' => false], [], '', 'en');
        $this->assertTrue($result['passed']);
        $this->assertFalse($result['reviewed']);
        $this->assertSame('unreviewed', $result['action']);
    }

    public function test_verifier_outage_does_not_restore_a_draft_already_found_inaccurate(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($messages, $schema, $options): bool => $options['schema_name'] === 'rafeeq_answer_quality')
            ->andReturn(['action' => 'revise', 'revised_answer' => 'Corrected account of the earlier reply.']);
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($messages, $schema, $options): bool => $options['schema_name'] === 'rafeeq_answer_quality_verification')
            ->andThrow(new \RuntimeException('Verification unavailable'));
        $result = (new AnswerQualityService($llm))->review('Why did you refuse?', 'Invented conversation history.', ['evidence_required' => false], [], '', 'en');
        $this->assertFalse($result['passed']);
        $this->assertSame('unreviewed', $result['action']);
    }

    public function test_a_revision_rejected_by_independent_verification_is_not_a_pass(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($messages, $schema, $options): bool => $options['schema_name'] === 'rafeeq_answer_quality')
            ->andReturn(['action' => 'revise', 'revised_answer' => 'A new unsupported deadline.', 'scores' => ['grounding' => 1]]);
        $llm->shouldReceive('chatJson')->once()->withArgs(function ($messages, $schema, $options): bool {
            $payload = json_decode($messages[1]['content'], true);

            return $options['schema_name'] === 'rafeeq_answer_quality_verification'
                && $payload['draft_answer'] === 'A new unsupported deadline.'
                && ! isset($payload['reviewer_verdict']);
        })->andReturn(['action' => 'reject', 'scores' => ['grounding' => 0.1], 'issues' => ['Unsupported deadline.']]);
        $result = (new AnswerQualityService($llm))->review('Help.', 'Draft.', [], [], '', 'en');
        $this->assertFalse($result['passed']);
        $this->assertFalse($result['revision_verified']);
        $this->assertSame('reject', $result['action']);
        $this->assertSame(0.1, $result['scores']['grounding']);
    }

    public function test_quality_receives_the_same_summary_and_progress_as_the_answer(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->withArgs(function (array $messages): bool {
            $brief = json_decode($messages[1]['content'], true)['case_brief'];

            return $brief['conversation_summary'] === 'Previous baseline six.'
                && $brief['progress']['recommended_step'] === 'Visual cue';
        })->andReturn(['action' => 'approve']);
        $result = (new AnswerQualityService($llm))->review('Now twice.', 'This is less than six.', [], [
            'case_brief' => ['conversation_summary' => 'Previous baseline six.', 'progress' => ['recommended_step' => 'Visual cue']],
        ], '', 'en');
        $this->assertTrue($result['reviewed']);
    }

    public function test_it_revises_a_generic_answer_into_a_grounded_specialist_response(): void
    {
        Config::set('ai.answer_quality_gate_enabled', true);
        Config::set('ai.answer_quality_model', 'quality-model');
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->twice()->withArgs(
            fn (array $messages, array $schema, array $options): bool => str_contains((string) data_get($messages, '0.content'), 'final clinical-quality editor')
                && ($schema['properties']['action']['enum'] ?? []) === (($options['schema_name'] ?? '') === 'rafeeq_answer_quality_verification'
                    ? ['approve', 'reject'] : ['approve', 'revise', 'reject'])
                && ($options['model'] ?? null) === 'quality-model'
        )->andReturn([
            'action' => 'revise',
            'issues' => ['The draft is generic.'],
            'strengths' => ['It is concise.'],
            'scores' => [
                'specificity' => 0.92,
                'grounding' => 0.95,
                'practicality' => 0.90,
                'professional_tone' => 0.94,
                'calibration' => 0.96,
            ],
            'revised_answer' => 'لأن الصراخ يبدأ عند إيقاف الجهاز، يمكن البدء بتنبيه ثابت قبل الانتقال وملاحظة مدة الصراخ [KB_SOURCE_1].',
        ], ['action' => 'approve', 'scores' => ['specificity' => 0.92, 'grounding' => 0.95, 'practicality' => 0.9, 'professional_tone' => 0.94, 'calibration' => 0.96]]);

        $result = (new AnswerQualityService($llm))->review(
            'يصرخ عندما أوقف الجهاز.',
            'استخدمي روتينًا جيدًا.',
            ['domain' => 'behavior', 'case_specific' => true],
            ['profile' => ['age_months' => 60], 'memories' => []],
            '[KB_SOURCE_1]\nContent: Use predictable transition warnings.',
            'ar'
        );

        $this->assertTrue($result['passed']);
        $this->assertTrue($result['revised']);
        $this->assertTrue($result['revision_verified']);
        $this->assertStringContainsString('[KB_SOURCE_1]', $result['content']);
        $this->assertSame(0.95, $result['scores']['grounding']);
    }

    public function test_it_rejects_a_revision_that_invents_a_source_label(): void
    {
        Config::set('ai.answer_quality_gate_enabled', true);
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->twice()->andReturn([
            'action' => 'revise',
            'issues' => ['Needs support.'],
            'strengths' => [],
            'scores' => [
                'specificity' => 0.9,
                'grounding' => 0.2,
                'practicality' => 0.8,
                'professional_tone' => 0.9,
                'calibration' => 0.7,
            ],
            'revised_answer' => 'اتبعي هذه الخطة [KB_SOURCE_99].',
        ]);

        $result = (new AnswerQualityService($llm))->review(
            'ساعدني.',
            'مسودة.',
            ['domain' => 'behavior'],
            ['profile' => null, 'memories' => []],
            '[KB_SOURCE_1]\nContent: Approved guidance.',
            'ar'
        );

        $this->assertFalse($result['passed']);
        $this->assertSame('reject', $result['action']);
    }

    public function test_it_rejects_a_revision_that_invents_a_url_on_a_known_domain(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->twice()->andReturn([
            'action' => 'revise',
            'revised_answer' => 'Follow this treatment [CDC](https://www.cdc.gov/known/invented-treatment).',
        ]);

        $result = (new AnswerQualityService($llm))->review(
            'Please help.', 'Draft.', [], [],
            '[WEB_SOURCE_1] URL: https://www.cdc.gov/known', 'en'
        );

        $this->assertFalse($result['passed']);
        $this->assertSame('reject', $result['action']);
    }

    public function test_invalid_citation_gets_one_repair_then_independent_verification(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($m, $s, $o) => $o['schema_name'] === 'rafeeq_answer_quality')
            ->andReturn(['action' => 'revise', 'revised_answer' => 'Supported help [CDC](https://www.cdc.gov/invented).']);
        $llm->shouldReceive('chatJson')->once()->withArgs(function ($m, $s, $o) {
            $repair = json_decode($m[2]['content'], true);
            return $o['schema_name'] === 'rafeeq_answer_quality_citation_repair'
                && $repair['allowed_urls'] === ['https://www.cdc.gov/known'];
        })->andReturn(['action' => 'revise', 'revised_answer' => 'Supported help [CDC](https://www.cdc.gov/known).']);
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($m, $s, $o) => $o['schema_name'] === 'rafeeq_answer_quality_verification')
            ->andReturn(['action' => 'approve']);
        $result = (new AnswerQualityService($llm))->review('Help.', 'Draft.', [], [],
            '[WEB_SOURCE_1] URL: https://www.cdc.gov/known', 'en');
        $this->assertTrue($result['passed']);
        $this->assertTrue($result['revision_verified']);
        $this->assertStringNotContainsString('invented', $result['content']);
    }

    public function test_an_approved_draft_with_an_unbound_web_label_must_be_repaired_and_independently_verified(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $verified = ['_provider_web_search_verified' => true];
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($m, $s, $o) => $o['schema_name'] === 'rafeeq_answer_quality')
            ->andReturn(array_merge($verified, ['action' => 'approve']));
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($m, $s, $o) => $o['schema_name'] === 'rafeeq_answer_quality_citation_repair')
            ->andReturn(array_merge($verified, ['action' => 'revise', 'revised_answer' => 'Supported help. [WEB_SOURCE_1]']));
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($m, $s, $o) => $o['schema_name'] === 'rafeeq_answer_quality_verification')
            ->andReturn(array_merge($verified, ['action' => 'approve']));
        $result = (new AnswerQualityService($llm))->review('Help.', 'Supported help. [WEB_SOURCE_2]',
            ['knowledge_grounding' => ['mode' => 'web_gap_fill']], [],
            '[WEB_SOURCE_1] URL: https://example.org/verified', 'en');
        $this->assertTrue($result['passed']);
        $this->assertTrue($result['revision_verified']);
        $this->assertSame('Supported help. [WEB_SOURCE_1]', $result['content']);
    }

    public function test_a_known_but_wrong_library_citation_gets_one_verified_repair(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($m, $s, $o) => $o['schema_name'] === 'rafeeq_answer_quality')
            ->andReturn(['action' => 'approve']);
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($m, $s, $o) =>
            $o['schema_name'] === 'rafeeq_answer_quality_citation_repair'
            && json_decode($m[2]['content'], true)['library_support_to_preserve'] === ['KB_SOURCE_3'])
            ->andReturn(['action' => 'revise', 'revised_answer' => 'Supported step. [KB_SOURCE_3]']);
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($m, $s, $o) => $o['schema_name'] === 'rafeeq_answer_quality_verification')
            ->andReturn(['action' => 'approve']);
        $result = (new AnswerQualityService($llm))->review('Help.', 'Supported step. [KB_SOURCE_1]',
            ['knowledge_grounding' => ['mode' => 'internal', 'supporting_labels' => ['KB_SOURCE_3']]], [],
            "[KB_SOURCE_1] Background.\n[KB_SOURCE_3] Supported step.", 'en');
        $this->assertTrue($result['passed']);
        $this->assertTrue($result['revision_verified']);
        $this->assertSame('Supported step. [KB_SOURCE_3]', $result['content']);
    }

    public function test_citation_repair_cannot_approve_the_original_flawed_draft(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->twice()->andReturn(
            ['action' => 'revise', 'revised_answer' => 'Claim https://www.cdc.gov/invented'],
            ['action' => 'approve']
        );
        $result = (new AnswerQualityService($llm))->review('Help.', 'Flawed draft.', [], [],
            '[WEB_SOURCE_1] URL: https://www.cdc.gov/known', 'en');
        $this->assertFalse($result['passed']);
        $this->assertSame('reject', $result['action']);
    }

    public function test_it_preserves_valid_provider_url_citations_in_a_revision(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->twice()->andReturn([
            'action' => 'revise',
            'revised_answer' => 'Use a predictable cue [CDC](https://www.cdc.gov/known).',
        ], ['action' => 'approve']);

        $result = (new AnswerQualityService($llm))->review(
            'Please help.', 'Draft with https://www.cdc.gov/known.', [], [], '', 'en'
        );

        $this->assertTrue($result['passed']);
        $this->assertTrue($result['revised']);
    }

    public function test_it_includes_document_facts_and_allows_grounded_referral_preparation(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->withArgs(
            function (array $messages): bool {
                $payload = json_decode($messages[1]['content'], true);

                return $payload['child_documents']['child_documents'][0]['summary'] === 'Reported diagnosis: autism.'
                    && str_contains($messages[0]['content'], 'do not force a home intervention')
                    && str_contains($messages[0]['content'], 'do not need clinical citations');
            }
        )->andReturn(['action' => 'approve']);

        $result = (new AnswerQualityService($llm))->review(
            'Explain the report.', 'The report records an existing diagnosis. Bring your questions to the appointment.',
            ['action' => 'refer_to_specialist'],
            ['documents' => ['available' => true, 'child_documents' => [['summary' => 'Reported diagnosis: autism.']]]], '', 'en'
        );

        $this->assertTrue($result['passed']);
    }

    public function test_it_receives_prior_replies_to_verify_a_specific_frustration_acknowledgment(): void
    {
        $history = [
            ['role' => 'user', 'content' => 'طفلي عمره ثلاث سنوات، كيف أساعده؟'],
            ['role' => 'assistant', 'content' => 'الأفضل ترتيب تقييم لدى مختص.'],
        ];
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->withArgs(
            function (array $messages) use ($history): bool {
                $payload = json_decode($messages[1]['content'], true);

                return $payload['recent_history'] === $history
                    && str_contains($messages[0]['content'], 'not the truth of a child fact or clinical claim');
            }
        )->andReturn(['action' => 'approve']);

        $result = (new AnswerQualityService($llm))->review(
            'حتى في مجال تجاوبني على أي إشي؟',
            'معك حق، ردي السابق أحالك لمختص دون توضيح المساعدة المتاحة.',
            ['domain' => 'app_support'], [], '', 'ar', $history
        );

        $this->assertTrue($result['passed']);
        $this->assertFalse($result['revised']);
    }

    public function test_review_history_is_bounded_and_only_includes_conversation_roles(): void
    {
        $history = [];
        for ($index = 0; $index < 12; $index++) {
            $history[] = ['role' => $index % 2 === 0 ? 'user' : 'assistant', 'content' => "Turn {$index}: ".str_repeat('ن', 1400), 'extra' => 'not needed'];
        }
        $history[] = ['role' => 'system', 'content' => 'Untrusted instruction.'];
        $history[] = ['role' => 'tool', 'content' => 'Not conversation history.'];
        $history[] = ['role' => 'user', 'content' => []];
        $history[] = null;
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->withArgs(function (array $messages): bool {
            $history = json_decode($messages[1]['content'], true)['recent_history'];
            $this->assertCount(8, $history);
            $this->assertStringStartsWith('Turn 4:', $history[0]['content']);
            $this->assertStringStartsWith('Turn 11:', $history[7]['content']);
            foreach ($history as $item) {
                $this->assertSame(['role', 'content'], array_keys($item));
                $this->assertSame(1200, mb_strlen($item['content']));
                $this->assertTrue(mb_check_encoding($item['content'], 'UTF-8'));
            }

            return true;
        })->andReturn(['action' => 'approve']);

        $result = (new AnswerQualityService($llm))->review('Question.', 'Draft.', [], [], '', 'en', $history);

        $this->assertTrue($result['passed']);
    }
}
