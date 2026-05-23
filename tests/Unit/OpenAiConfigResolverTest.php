<?php

namespace Tests\Unit;

use App\Services\AI\OpenAiConfigResolver;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class OpenAiConfigResolverTest extends TestCase
{
    public function test_it_reads_openai_credentials_from_env_file_when_cached_config_is_empty(): void
    {
        Config::set('ai.openai_api_key', null);
        Config::set('openai.api_key', null);
        Config::set('openai.organization', null);

        $envPath = storage_path('framework/testing/openai-config-resolver.env');

        file_put_contents($envPath, <<<'ENV'
OPENAI_API_KEY=test-runtime-key
OPENAI_ORGANIZATION=test-org
ENV);

        $resolver = new OpenAiConfigResolver($envPath);

        $this->assertSame('test-runtime-key', $resolver->apiKey());
        $this->assertSame('test-org', $resolver->organization());

        $resolver->syncIntoRuntimeConfig();

        $this->assertSame('test-runtime-key', config('ai.openai_api_key'));
        $this->assertSame('test-runtime-key', config('openai.api_key'));
        $this->assertSame('test-org', config('openai.organization'));

        @unlink($envPath);
    }

    public function test_it_supports_the_legacy_ai_openai_api_key_name(): void
    {
        Config::set('ai.openai_api_key', null);
        Config::set('openai.api_key', null);

        $envPath = storage_path('framework/testing/openai-config-legacy.env');

        file_put_contents($envPath, <<<'ENV'
AI_OPENAI_API_KEY=legacy-runtime-key
ENV);

        $resolver = new OpenAiConfigResolver($envPath);

        $this->assertSame('legacy-runtime-key', $resolver->apiKey());

        @unlink($envPath);
    }
}
