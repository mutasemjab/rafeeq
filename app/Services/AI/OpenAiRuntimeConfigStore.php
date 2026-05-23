<?php

namespace App\Services\AI;

class OpenAiRuntimeConfigStore
{
    private ?array $cached = null;

    public function __construct(private ?string $path = null)
    {
    }

    public function apiKey(): ?string
    {
        return $this->normalize($this->read()['api_key'] ?? null);
    }

    public function organization(): ?string
    {
        return $this->normalize($this->read()['organization'] ?? null);
    }

    public function save(string $apiKey, ?string $organization = null): void
    {
        $payload = [
            'api_key'      => trim($apiKey),
            'organization' => $this->normalize($organization),
            'updated_at'   => now()->toIso8601String(),
        ];

        $directory = dirname($this->path());

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $this->path(),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            LOCK_EX
        );

        @chmod($this->path(), 0600);

        $this->cached = $payload;
    }

    public function exists(): bool
    {
        return is_file($this->path()) && is_readable($this->path());
    }

    public function path(): string
    {
        return $this->path ?? storage_path('app/private/openai-runtime.json');
    }

    private function read(): array
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        if (! $this->exists()) {
            return $this->cached = [];
        }

        $contents = file_get_contents($this->path());

        if ($contents === false || trim($contents) === '') {
            return $this->cached = [];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->cached = [];
        }

        return $this->cached = is_array($decoded) ? $decoded : [];
    }

    private function normalize(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
