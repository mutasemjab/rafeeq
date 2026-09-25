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
                return ($schema['properties']['question']['type'] ?? null) === ['string', 'null']
                    && ($options['schema_name'] ?? null) === 'rafeeq_follow_up'
                    && str_contains((string) data_get($messages, '1.content'), '"response_language":"ar"');
            })
            ->andReturn([
                'question' => 'بعد تطبيق الخطوة ثلاثة أيام، كم مرة حدث الصراخ؟',
                'purpose' => 'outcome_check',
                'wait_for_observation' => true,
            ]);

        $result = (new FollowUpSuggestionService($llm))->suggest(
            'ابني يصرخ كثيرًا',
            'ابدئي بتسجيل الموقف.',
            ['domain' => 'behavior'],
            ['profile' => ['age' => 5], 'memories' => []],
            [],
            'ar'
        );

        $this->assertSame('بعد تطبيق الخطوة ثلاثة أيام، كم مرة حدث الصراخ؟', $result['question']);
        $this->assertTrue($result['wait_for_observation']);
    }
}
