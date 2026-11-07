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
    public function test_only_assessed_answer_gaps_allow_web_fallback(): void
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
        $evidenceMethod = new ReflectionMethod($service, 'requiresKnowledgeEvidence');
        $this->assertTrue($evidenceMethod->invoke($service, ['domain' => 'appetite'], []));
        $this->assertTrue($evidenceMethod->invoke($service, ['domain' => 'communication', 'evidence_required' => false], ['general_education' => true]));
        $this->assertTrue($evidenceMethod->invoke($service, ['domain' => 'app_support', 'evidence_required' => true], []));
        $this->assertFalse($evidenceMethod->invoke($service, ['domain' => 'app_support'], []));
        $this->assertFalse($evidenceMethod->invoke($service, ['domain' => 'app_support', 'evidence_required' => false], []));
        $method = new ReflectionMethod($service, 'needsWebFallback');
        $this->assertFalse($method->invoke($service, ['sufficient' => true]));
        $this->assertTrue($method->invoke($service, ['sufficient' => false]));
    }
}
