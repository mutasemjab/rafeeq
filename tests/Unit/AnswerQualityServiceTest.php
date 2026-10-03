<?php

namespace Tests\Unit;

use App\Services\AI\AnswerQualityService;
use App\Services\AI\Contracts\LlmProviderInterface;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class AnswerQualityServiceTest extends TestCase
{
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
                && ($schema['properties']['action']['enum'] ?? []) === ['approve', 'revise', 'reject']
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
        $llm->shouldReceive('chatJson')->once()->andReturn([
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
        $llm->shouldReceive('chatJson')->once()->andReturn([
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
