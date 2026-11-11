<?php

namespace Tests\Unit;

use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\AI\KnowledgeEvidenceService;
use Mockery;
use Tests\TestCase;

class KnowledgeEvidenceServiceTest extends TestCase
{
    public function test_prerequisite_clarification_uses_a_real_excerpt_and_constrained_source_labels(): void
    {
        config(['ai.personalized_knowledge_coverage_model' => 'careful-coverage']);
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->withArgs(fn ($m, $schema, $options) =>
            $schema['properties']['supporting_quotes']['items']['properties']['source_label']['enum'] === ['KB_SOURCE_7']
            && $options['model'] === 'careful-coverage' && $options['reasoning_effort'] === 'low')
            ->andReturn(['sufficient' => false, 'supporting_quotes' => [['source_label' => 'KB_SOURCE_7', 'excerpt_id' => 1]],
                'missing_information' => ['The prerequisite ability is unknown.'], 'search_queries' => [],
                'clarification_question' => 'Can the child follow a simple familiar instruction?']);
        $result = (new KnowledgeEvidenceService($llm))->assess('How do I start?', ['case_specific' => true], [],
            [['source_label' => 'KB_SOURCE_7', 'content' => 'Prerequisite: following a simple instruction.']]);
        $this->assertFalse($result['sufficient']);
        $this->assertNotNull($result['clarification_question']);
    }

    public function test_a_general_question_cannot_be_replaced_with_a_personal_prerequisite_question(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->twice()->andReturn(['sufficient' => false,
            'supporting_quotes' => [['source_label' => 'KB_SOURCE_1', 'excerpt_id' => 1]],
            'missing_information' => ['Unknown readiness.'], 'search_queries' => [], 'clarification_question' => 'How old is your child?']);
        $this->expectException(\RuntimeException::class);
        (new KnowledgeEvidenceService($llm))->assess('Define communication.', ['case_specific' => false], [],
            [['source_label' => 'KB_SOURCE_1', 'content' => 'Communication is sharing information.']]);
    }

    public function test_pdf_line_wraps_use_stable_excerpt_references(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->andReturn(['sufficient' => true,
            'supporting_quotes' => [['source_label' => 'KB_SOURCE_1', 'excerpt_id' => 1]],
            'missing_information' => [], 'search_queries' => []]);
        $result = (new KnowledgeEvidenceService($llm))->assess('How is voice produced?', [], [], [
            ['source_label' => 'KB_SOURCE_1', 'content' => "The exhaled breath causes vibration\nwhen the vocal folds meet."],
        ]);
        $this->assertTrue($result['sufficient']);
    }

    public function test_an_invalid_excerpt_id_is_repaired_against_the_same_evidence(): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $base = ['sufficient' => true, 'missing_information' => [], 'search_queries' => []];
        $llm->shouldReceive('chatJson')->twice()->andReturn(
            array_merge($base, ['supporting_quotes' => [['source_label' => 'KB_SOURCE_1', 'excerpt_id' => 99]]]),
            array_merge($base, ['supporting_quotes' => [['source_label' => 'KB_SOURCE_1', 'excerpt_id' => 1]]])
        );
        $result = (new KnowledgeEvidenceService($llm))->assess('ما أسس التواصل؟', [], [], [
            ['source_label' => 'KB_SOURCE_1', 'content' => 'أربعة أسس يقوم عليها ت التواصل الفعال. الكمية: إعطاء كمية معلومات كافية.'],
        ]);
        $this->assertTrue($result['sufficient']);
        $this->assertSame(['KB_SOURCE_1'], $result['supporting_labels']);
    }

    /** @dataProvider invalidProof */
    public function test_missing_or_fabricated_support_is_not_a_valid_coverage_decision(array $result): void
    {
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->twice()->andReturn($result);
        $this->expectException(\RuntimeException::class);
        (new KnowledgeEvidenceService($llm))->assess('What is communication?', [], [], [
            ['source_label' => 'KB_SOURCE_1', 'content' => 'Communication involves exchanging information.'],
        ]);
    }

    public static function invalidProof(): array
    {
        $base = ['sufficient' => true, 'supporting_quotes' => [], 'missing_information' => [], 'search_queries' => []];
        return [[$base], [array_merge($base, ['supporting_quotes' => [['source_label' => 'KB_SOURCE_99', 'excerpt_id' => 1]]])],
            [array_merge($base, ['supporting_quotes' => [['source_label' => 'KB_SOURCE_1', 'excerpt_id' => 99]]])],
            [array_merge($base, ['sufficient' => false])], [[]]];
    }

    public function test_current_information_is_assessed_against_the_passage_rather_than_forcing_web(): void
    {
        config(['ai.knowledge_coverage_model' => 'fast-coverage']);
        $llm = Mockery::mock(LlmProviderInterface::class);
        $llm->shouldReceive('chatJson')->once()->withArgs(function ($messages, $schema, $options) {
            $payload = json_decode($messages[1]['content'], true);
            return $payload['current_information_requested'] === true && isset($payload['current_date'])
                && $options['model'] === 'fast-coverage' && $options['reasoning_effort'] === 'none';
        })->andReturn(['sufficient' => false, 'supporting_quotes' => [],
            'missing_information' => ['The requested current figure is absent.'], 'search_queries' => ['current figure']]);
        $result = (new KnowledgeEvidenceService($llm))->assess('Latest figure?', ['current_information_requested' => true, 'case_specific' => false], [], [
            ['source_label' => 'KB_SOURCE_1', 'content' => 'A figure published in 1990.'],
        ]);
        $this->assertFalse($result['sufficient']);
        $this->assertSame(['current figure'], $result['search_queries']);
    }
}
