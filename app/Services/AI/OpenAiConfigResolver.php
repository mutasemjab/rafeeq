<?php

namespace App\Services\AI;

class OpenAiConfigResolver
{
    private ?string $resolvedApiKey = null;
    private ?string $resolvedOrganization = null;
    private bool $resolved = false;

    public function __construct(private ?string $envPath = null)
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

        $this->resolvedApiKey = $this->resolveValue(
            ['ai.openai_api_key', 'openai.api_key'],
            ['OPENAI_API_KEY', 'AI_OPENAI_API_KEY']
        );

        $this->resolvedOrganization = $this->resolveValue(
            ['openai.organization'],
            ['OPENAI_ORGANIZATION']
        );

        $this->resolved = true;
    }

    private function resolveValue(array $configKeys, array $envKeys): ?string
    {
        foreach ($configKeys as $configKey) {
            $value = $this->normalize(config($configKey));

            if ($value !== null) {
                return $value;
            }
        }

        foreach ($envKeys as $envKey) {
            $value = $this->envFileValue($envKey);

            if ($value !== null) {
                return $value;
            }
        }

        foreach ($envKeys as $envKey) {
            $value = $this->normalize($_SERVER[$envKey] ?? $_ENV[$envKey] ?? getenv($envKey) ?: null);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function envFileValue(string $key): ?string
    {
        $path = $this->envPath ?? base_path('.env');

        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return null;
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

            return $this->normalize($value);
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
