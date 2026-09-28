<?php

namespace Tests\Unit;

use App\Services\AI\AnswerQualityService;
use App\Services\AI\Contracts\LlmProviderInterface;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class AnswerQualityServiceTest extends TestCase
{
    public function test_it_revises_a_generic_answer_into_a_grounded_specialist_response(): void
    {
        Config::set('ai.answer_quality_gate_enabled', true);
        Config::set('ai.answer_quality_model', 'quality-model');
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->withArgs(
            fn (array $messages, array $schema, array $options): bool =>
                str_contains((string) data_get($messages, '0.content'), 'final clinical-quality editor')
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
            'revised_answer' => 'لأن الصراخ يبدأ عند إيقاف الجهاز، ابدئي بتنبيه ثابت قبل الانتقال وراقبي مدة الصراخ لمدة خمسة أيام [KB_SOURCE_1].',
        ]);

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
}
