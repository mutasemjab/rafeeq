<?php

namespace App\Services\AI;

class OpenAiConfigResolver
{
    private ?string $resolvedApiKey = null;
    private ?string $resolvedOrganization = null;
    private ?string $resolvedApiKeySource = null;
    private ?string $resolvedOrganizationSource = null;
    private bool $resolved = false;

    public function __construct(
        private ?string $envPath = null,
        private ?OpenAiRuntimeConfigStore $runtimeConfigStore = null
    )
    {
    }

    public function apiKey(): ?string
    {
        $this->resolve();

        return $this->resolvedApiKey;
    }

    public function organization(): ?string
    {
        $this->resolve();

        return $this->resolvedOrganization;
    }

    public function apiKeySource(): ?string
    {
        $this->resolve();

        return $this->resolvedApiKeySource;
    }

    public function syncIntoRuntimeConfig(): void
    {
        $apiKey = $this->apiKey();

        if ($apiKey !== null) {
            config([
                'ai.openai_api_key' => $apiKey,
                'openai.api_key'    => $apiKey,
            ]);
        }

        $organization = $this->organization();

        if ($organization !== null) {
            config([
                'openai.organization' => $organization,
            ]);
        }
    }

    private function resolve(): void
    {
        if ($this->resolved) {
            return;
        }

        [$this->resolvedApiKey, $this->resolvedApiKeySource] = $this->resolveValue(
            ['ai.openai_api_key', 'openai.api_key'],
            ['OPENAI_API_KEY', 'AI_OPENAI_API_KEY']
        );

        [$this->resolvedOrganization, $this->resolvedOrganizationSource] = $this->resolveValue(
            ['openai.organization'],
            ['OPENAI_ORGANIZATION']
        );

        $this->resolved = true;
    }

    private function resolveValue(array $configKeys, array $envKeys): array
    {
        foreach ($configKeys as $configKey) {
            $value = $this->normalize(config($configKey));

            if ($value !== null) {
                return [$value, 'config'];
            }
        }

        foreach ($envKeys as $envKey) {
            $value = $this->envFileValue($envKey);

            if ($value !== null) {
                return [$value, 'env_file'];
            }
        }

        foreach ($envKeys as $envKey) {
            $value = $this->normalize($_SERVER[$envKey] ?? $_ENV[$envKey] ?? getenv($envKey) ?: null);

            if ($value !== null) {
                return [$value, 'server_env'];
            }
        }

        $runtimeValue = $this->runtimeConfigValue($envKeys);

        if ($runtimeValue !== null) {
            return [$runtimeValue, 'runtime_file'];
        }

        return [null, null];
    }

    private function envFileValue(string $key): ?string
    {
        foreach ($this->envPaths() as $path) {
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            if ($lines === false) {
                continue;
            }

            foreach ($lines as $line) {
                $trimmed = trim($line);

                if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                    continue;
                }

                if (str_starts_with($trimmed, 'export ')) {
                    $trimmed = substr($trimmed, 7);
                }

                [$name, $value] = array_pad(explode('=', $trimmed, 2), 2, null);

                if (trim((string) $name) !== $key || $value === null) {
                    continue;
                }

                $normalized = $this->normalize($value);

                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        return null;
    }

    private function envPaths(): array
    {
        if ($this->envPath !== null) {
            return [$this->envPath];
        }

        return [
            base_path('.env'),
            base_path('.enve'),
        ];
    }

    private function runtimeConfigValue(array $envKeys): ?string
    {
        $store = $this->runtimeConfigStore;

        if ($store === null) {
            return null;
        }

        if (in_array('OPENAI_ORGANIZATION', $envKeys, true)) {
            return $store->organization();
        }

        if (array_intersect($envKeys, ['OPENAI_API_KEY', 'AI_OPENAI_API_KEY']) !== []) {
            return $store->apiKey();
        }

        return null;
    }

    private function normalize(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return trim($trimmed, "\"'");
    }
}
