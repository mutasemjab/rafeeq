<?php

namespace Tests\Unit;

use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\AI\FollowUpSuggestionService;
use Mockery;
use Tests\TestCase;

class FollowUpSuggestionServiceTest extends TestCase
{
    public function test_it_returns_one_structured_next_question_in_the_requested_language(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')
            ->once()
            ->withArgs(function (array $messages, array $schema, array $options): bool {
                $payload = (string) data_get($messages, '1.content');

                return ($schema['properties']['question']['type'] ?? null) === ['string', 'null']
                    && in_array('decision_impact', $schema['required'] ?? [], true)
                    && ($options['schema_name'] ?? null) === 'rafeeq_follow_up'
                    && str_contains($payload, '"response_language":"ar"')
                    && str_contains($payload, '"asked_questions"');
            })
            ->andReturn([
                'question' => 'بعد تطبيق الخطوة ثلاثة أيام، كم مرة حدث الصراخ؟',
                'purpose' => 'outcome_check',
                'wait_for_observation' => true,
                'anchor' => 'تطبيق الخطوة ثلاثة أيام',
                'decision_impact' => 'تحديد ما إذا كنا سنثبت الخطوة أو نعدّلها.',
            ]);

        $result = (new FollowUpSuggestionService($llm))->suggest(
            'ابني يصرخ كثيرًا',
            'ابدئي بتسجيل الموقف.',
            ['domain' => 'behavior'],
            ['profile' => ['age' => 5], 'memories' => []],
            [],
            'ar',
            [
                'asked_questions' => [[
                    'question' => 'ماذا يحدث قبل الصراخ؟',
                    'target' => 'antecedent',
                ]],
            ]
        );

        $this->assertSame('بعد تطبيق الخطوة ثلاثة أيام، كم مرة حدث الصراخ؟', $result['question']);
        $this->assertTrue($result['wait_for_observation']);
        $this->assertSame('تطبيق الخطوة ثلاثة أيام', $result['anchor']);
        $this->assertStringContainsString('نعدّلها', $result['decision_impact']);
    }
}
