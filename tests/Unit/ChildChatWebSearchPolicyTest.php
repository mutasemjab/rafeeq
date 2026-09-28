<?php

namespace Tests\Unit;

use App\Services\AI\ChatTurnPlannerService;
use App\Services\AI\ChildChatService;
use App\Services\AI\ChildContextService;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\AI\DomainGuardService;
use App\Services\AI\SafetyTriageService;
use App\Services\Search\ChatAttachmentSearchService;
use App\Services\Search\Contracts\WebSearchServiceInterface;
use App\Services\Search\KnowledgeSearchService;
use Illuminate\Support\Facades\Config;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class ChildChatWebSearchPolicyTest extends TestCase
{
    public function test_strong_internal_evidence_skips_web_while_missing_or_current_evidence_requires_it(): void
    {
        Config::set('ai.openai_web_search_enabled', true);
        Config::set('ai.web_search_internal_confidence_threshold', 0.68);
        $service = new ChildChatService(
            Mockery::mock(LlmProviderInterface::class),
            Mockery::mock(ChildContextService::class),
            Mockery::mock(KnowledgeSearchService::class),
            Mockery::mock(ChatAttachmentSearchService::class),
            Mockery::mock(WebSearchServiceInterface::class),
            Mockery::mock(DomainGuardService::class),
            Mockery::mock(SafetyTriageService::class),
            Mockery::mock(ChatTurnPlannerService::class)
        );
        $method = new ReflectionMethod($service, 'shouldUseHostedWebSearch');
        $plan = [
            'domain' => 'behavior',
            'evidence_required' => true,
            'web_search_needed' => false,
            'risk_level' => 'low',
        ];

        $this->assertFalse($method->invoke($service, $plan, [['retrieval_score' => 0.82]], []));
        $this->assertTrue($method->invoke($service, $plan, [], []));
        $this->assertTrue($method->invoke(
            $service,
            array_merge($plan, ['web_search_needed' => true]),
            [['retrieval_score' => 0.90]],
            []
        ));
    }
}
