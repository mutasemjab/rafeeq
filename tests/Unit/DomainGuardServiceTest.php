<?php

namespace Tests\Unit;

use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\AI\DomainGuardService;
use Illuminate\Support\Facades\Config;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DomainGuardServiceTest extends TestCase
{
    public function test_it_allows_a_confident_rafeeq_question(): void
    {
        Config::set('ai.domain_guard_confidence', 0.85);
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andReturn([
            'allowed' => true,
            'confidence' => 0.98,
            'category' => 'speech_language',
            'reason' => 'Question is about a child speech delay.',
        ]);

        $result = (new DomainGuardService($llm))->evaluate(
            'How can I help my child pronounce the R sound?'
        );

        $this->assertTrue($result['allowed']);
        $this->assertSame('speech_language', $result['category']);
    }

    public function test_it_blocks_an_unrelated_question(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andReturn([
            'allowed' => false,
            'confidence' => 0.99,
            'category' => 'coding',
            'reason' => 'Coding is outside Rafiq scope.',
        ]);

        $result = (new DomainGuardService($llm))->evaluate('Write a Python web scraper.');

        $this->assertFalse($result['allowed']);
    }

    public function test_it_returns_bounded_english_search_queries_for_allowed_arabic_questions(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andReturn([
            'allowed' => true,
            'confidence' => 0.99,
            'category' => 'feeding_and_language',
            'reason' => 'Both questions are within Rafiq scope.',
            'search_queries' => [
                'five stages of normal swallowing',
                'indicators that distinguish late talkers from late bloomers',
            ],
        ]);

        $result = (new DomainGuardService($llm))->evaluate(
            'ما مراحل البلع؟ وما الفرق بين متأخر الكلام والمتفتح لغويًا متأخرًا؟'
        );

        $this->assertSame([
            'five stages of normal swallowing',
            'indicators that distinguish late talkers from late bloomers',
        ], $result['search_queries']);
    }

    public function test_it_fails_closed_when_the_classifier_is_unavailable(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andThrow(new RuntimeException('timeout'));

        $result = (new DomainGuardService($llm))->evaluate('Tell me something.');

        $this->assertFalse($result['allowed']);
        $this->assertSame('guard_error', $result['category']);
    }
}
