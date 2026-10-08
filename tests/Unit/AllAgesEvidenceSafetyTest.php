<?php

namespace Tests\Unit;

use App\Models\KnowledgeDocument;
use App\Models\KnowledgeDocumentChunk;
use App\Repositories\MysqlVectorSearchRepository;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\AI\SafetyTriageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AllAgesEvidenceSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_adult_evidence_excludes_child_only_and_unscoped_legacy_documents(): void
    {
        config(['ai.embedding_model' => 'test-model']);
        foreach ([
            ['title' => 'Child-only', 'age_max_months' => 216],
            ['title' => 'Unscoped legacy'],
            ['title' => 'Adult', 'age_min_months' => 216],
            ['title' => 'All ages', 'audience' => 'all_ages'],
            ['title' => 'Mislabeled bounded', 'audience' => 'all_ages', 'age_max_months' => 100],
        ] as $attributes) {
            $document = KnowledgeDocument::create(array_merge([
                'original_name' => 'guide.txt', 'file_path' => 'knowledge/guide.txt',
                'status' => 'processed', 'is_approved' => true,
            ], $attributes));
            KnowledgeDocumentChunk::create(['knowledge_document_id' => $document->id, 'chunk_index' => 0,
                'content' => $document->title, 'embedding' => '[1.0,0.0]', 'embedding_dimensions' => 2,
                'metadata' => ['embedding_model' => 'test-model']]);
        }
        $repo = new MysqlVectorSearchRepository();
        $results = $repo->searchKnowledge([1.0, 0.0], 10, 0, ['age_months' => 480, 'require_age_scope' => true]);
        $this->assertEqualsCanonicalizing(['Adult', 'All ages'], array_column($results, 'title'));
        $results = $repo->searchKnowledge([1.0, 0.0], 10, 0, ['require_age_scope' => true]);
        $this->assertSame(['All ages'], array_column($results, 'title'));
    }

    /** @dataProvider adultSafetyCues */
    public function test_first_person_and_adult_safety_cues_reach_contextual_triage(string $message): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andReturn([
            'level' => 'urgent_specialist', 'reason_code' => 'test_contextual_concern',
            'reason' => 'Needs contextual assessment', 'confidence' => 0.9, 'flags' => [],
        ]);
        $result = (new SafetyTriageService($llm))->evaluate($message);
        $this->assertSame('urgent_specialist', $result['level']);
    }

    public static function adultSafetyCues(): array
    {
        return [['أفكر أن أؤذي نفسي'], ['I might hurt myself'], ['I have concerns about withdrawal'], ['أخشى أخذ جرعة زائدة']];
    }
}
