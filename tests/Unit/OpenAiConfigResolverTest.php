<?php

namespace Tests\Unit;

use App\Services\AI\OpenAiConfigResolver;
use App\Services\AI\OpenAiRuntimeConfigStore;
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

    public function test_it_reads_openai_credentials_from_runtime_store_when_env_is_unavailable(): void
    {
        Config::set('ai.openai_api_key', null);
        Config::set('openai.api_key', null);
        Config::set('openai.organization', null);

        $originalOpenAiKey = getenv('OPENAI_API_KEY');
        $originalLegacyKey = getenv('AI_OPENAI_API_KEY');
        $originalOrganization = getenv('OPENAI_ORGANIZATION');

        putenv('OPENAI_API_KEY');
        putenv('AI_OPENAI_API_KEY');
        putenv('OPENAI_ORGANIZATION');
        unset($_ENV['OPENAI_API_KEY'], $_SERVER['OPENAI_API_KEY']);
        unset($_ENV['AI_OPENAI_API_KEY'], $_SERVER['AI_OPENAI_API_KEY']);
        unset($_ENV['OPENAI_ORGANIZATION'], $_SERVER['OPENAI_ORGANIZATION']);

        $storePath = storage_path('framework/testing/openai-runtime-config.json');
        $store = new OpenAiRuntimeConfigStore($storePath);
        $store->save('runtime-file-key', 'runtime-org');

        $resolver = new OpenAiConfigResolver(
            storage_path('framework/testing/missing-runtime-source.env'),
            $store
        );

        $this->assertSame('runtime-file-key', $resolver->apiKey());
        $this->assertSame('runtime_file', $resolver->apiKeySource());
        $this->assertSame('runtime-org', $resolver->organization());

        @unlink($storePath);

        if ($originalOpenAiKey !== false) {
            putenv('OPENAI_API_KEY=' . $originalOpenAiKey);
            $_ENV['OPENAI_API_KEY'] = $originalOpenAiKey;
            $_SERVER['OPENAI_API_KEY'] = $originalOpenAiKey;
        }

        if ($originalLegacyKey !== false) {
            putenv('AI_OPENAI_API_KEY=' . $originalLegacyKey);
            $_ENV['AI_OPENAI_API_KEY'] = $originalLegacyKey;
            $_SERVER['AI_OPENAI_API_KEY'] = $originalLegacyKey;
        }

        if ($originalOrganization !== false) {
            putenv('OPENAI_ORGANIZATION=' . $originalOrganization);
            $_ENV['OPENAI_ORGANIZATION'] = $originalOrganization;
            $_SERVER['OPENAI_ORGANIZATION'] = $originalOrganization;
        }
    }
}
