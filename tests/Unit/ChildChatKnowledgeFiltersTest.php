<?php

namespace Tests\Unit;

use App\Services\AI\ChildChatService;
use ReflectionClass;
use Tests\TestCase;

class ChildChatKnowledgeFiltersTest extends TestCase
{
    public function test_an_uncorrected_profile_retains_its_typed_age_filter(): void
    {
        $this->assertSame(36, $this->filters(['profile' => ['age_months' => 36]])['age_months']);
    }

    /** @dataProvider reportedAgeContexts */
    public function test_reported_age_facts_prevent_a_stale_profile_from_excluding_sources(array $brief): void
    {
        $context = ['profile' => ['age_months' => 36, 'age' => 3], 'case_brief' => $brief];
        $filters = $this->filters($context);
        $this->assertArrayNotHasKey('age_months', $filters);
        $this->assertSame('speech_language', $filters['domain']);
        $this->assertTrue($filters['approved_only']);
        $this->assertSame(36, $context['profile']['age_months']);
    }

    public static function reportedAgeContexts(): array
    {
        return [
            [['current_facts' => ['child.age' => ['content' => 'أقصد خمس سنوات', 'source_message_id' => 22]]]],
            [['reported_facts' => [['key' => 'child.birth_date', 'content' => 'صححت تاريخ ميلاده', 'source_message_id' => 23]]]],
        ];
    }

    private function filters(array $context): array
    {
        $reflection = new ReflectionClass(ChildChatService::class);

        return $reflection->getMethod('knowledgeFilters')->invoke($reflection->newInstanceWithoutConstructor(), $context, ['domain' => 'speech_language'], 'ar');
    }
}
