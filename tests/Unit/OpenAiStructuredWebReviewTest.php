<?php

namespace Tests\Unit;

use App\Services\AI\OpenAiConfigResolver;
use App\Services\AI\Providers\OpenAiProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenAiStructuredWebReviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.openai_api_key' => 'test-key', 'openai.api_key' => 'test-key',
            'ai.openai_web_search_enabled' => true, 'ai.openai_responses_fail_open' => true]);
        Http::preventStrayRequests();
    }

    private function review(): array
    {
        return (new OpenAiProvider(new OpenAiConfigResolver()))->chatJson(
            [['role' => 'user', 'content' => 'Verify this public source.']], ['action' => 'string'],
            ['schema_name' => 'web_review', 'web_search' => true, 'model' => 'test-model', 'reasoning_effort' => 'none']
        );
    }

    public function test_structured_review_requires_a_completed_web_lookup_and_validates_the_json(): void
    {
        Http::fake(['api.openai.com/v1/responses' => Http::response([
            'output' => [['type' => 'web_search_call', 'status' => 'completed', 'action' => ['sources' => [
                ['url' => 'https://www.cdc.gov/report', 'title' => 'Report'],
            ]]]], 'output_text' => '{"action":"approve"}',
        ])]);
        $result = $this->review();
        $this->assertSame('approve', $result['action']);
        $this->assertTrue($result['_provider_web_search_verified']);
        $this->assertSame('https://www.cdc.gov/report', $result['_provider_web_sources'][0]['url']);
        Http::assertSent(fn ($request) => $request['tool_choice'] === 'required'
            && $request['text']['format']['type'] === 'json_schema'
            && $request['text']['format']['name'] === 'web_review'
            && $request['text']['format']['strict'] === true
            && $request['text']['format']['schema']['required'] === ['action']
            && $request['store'] === false);
    }

    /** @dataProvider incompleteLookups */
    public function test_a_missing_or_failed_lookup_cannot_claim_verification(array $output): void
    {
        Http::fake(['api.openai.com/v1/responses' => Http::response(['output' => $output, 'output_text' => '{"action":"approve"}'])]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Required web verification did not complete');
        $this->review();
    }

    public static function incompleteLookups(): array
    {
        return [[[]], [[['type' => 'web_search_call', 'status' => 'failed']]]];
    }

    public function test_invalid_structured_web_output_is_rejected(): void
    {
        Http::fake(['api.openai.com/v1/responses' => Http::response([
            'output' => [['type' => 'web_search_call', 'status' => 'completed']], 'output_text' => '{"action":42}',
        ])]);
        $this->expectException(RuntimeException::class);
        $this->review();
    }

    public function test_required_web_review_never_falls_back_after_provider_failure(): void
    {
        Http::fake(['api.openai.com/v1/responses' => Http::response(['error' => 'Unavailable'], 503)]);
        $this->expectException(RuntimeException::class);
        $this->review();
    }

    public function test_disabled_web_search_cannot_silently_approve_a_web_review(): void
    {
        config(['ai.openai_web_search_enabled' => false]);
        Http::fake();
        try {
            $this->review();
            $this->fail('Expected required verification to fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Required web verification is disabled', $exception->getMessage());
        }
        Http::assertNothingSent();
    }
}
