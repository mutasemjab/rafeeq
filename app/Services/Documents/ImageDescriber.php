<?php

namespace App\Services\Documents;

use App\Services\AI\OpenAiConfigResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ImageDescriber
{
    private const PROMPT_VERSION = 2;

    public function __construct(private OpenAiConfigResolver $configResolver)
    {
    }

    public function describe(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Image is missing or unreadable for vision extraction.');
        }

        $model = (string) config('ai.document_vision_model', 'gpt-5.6-luna');
        $detail = (string) config('ai.document_vision_detail', 'high');
        $hash = hash_file('sha256', $path);

        if (!is_string($hash)) {
            throw new RuntimeException('Unable to hash the image for vision extraction.');
        }

        $cachePath = $this->cachePath(hash(
            'sha256',
            $hash.'|'.$model.'|'.$detail.'|'.self::PROMPT_VERSION
        ));
        if (is_file($cachePath)) {
            $cached = json_decode((string) file_get_contents($cachePath), true);
            if (is_array($cached) && is_string($cached['text'] ?? null) && trim($cached['text']) !== '') {
                return trim($cached['text']);
            }
        }

        $apiKey = $this->configResolver->apiKey();
        if ($apiKey === null || trim($apiKey) === '') {
            throw new RuntimeException('OPENAI_API_KEY is required for visual-only document extraction.');
        }

        $mimeType = mime_content_type($path) ?: 'image/png';
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            throw new RuntimeException("Vision extraction does not accept image type {$mimeType}.");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Unable to read the image for vision extraction.');
        }

        $request = Http::withToken($apiKey)
            ->acceptJson()
            ->connectTimeout(20)
            ->timeout(180)
            ->retry(2, 1000);
        $organization = $this->configResolver->organization();
        if ($organization !== null && trim($organization) !== '') {
            $request = $request->withHeaders(['OpenAI-Organization' => trim($organization)]);
        }

        $response = $request->post('https://api.openai.com/v1/responses', [
            'model' => $model,
            'store' => false,
            'reasoning' => ['effort' => 'low'],
            'text' => ['verbosity' => 'low'],
            'max_output_tokens' => 700,
            'input' => [[
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => 'Create a concise factual knowledge-base description of this educational image. Focus on the objects, actions, sequence, comparison, or relationship shown. Transcribe Arabic or English words only when they are clearly legible; omit uncertain text instead of guessing. Do not repeat page headers, watermarks, or boilerplate. Use the main language visible in the image. Do not identify people, diagnose a condition, or invent facts. Return plain text only.',
                    ],
                    [
                        'type' => 'input_image',
                        'image_url' => 'data:'.$mimeType.';base64,'.base64_encode($contents),
                        'detail' => $detail,
                    ],
                ],
            ]],
        ]);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'OpenAI vision extraction failed with HTTP %d: %s',
                $response->status(),
                mb_substr((string) $response->body(), 0, 800)
            ));
        }

        $text = $this->responseText($response->json() ?: []);
        if ($text === '') {
            throw new RuntimeException('OpenAI vision extraction returned no text.');
        }

        $directory = dirname($cachePath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the vision cache directory.');
        }
        file_put_contents($cachePath, json_encode([
            'model' => $model,
            'detail' => $detail,
            'prompt_version' => self::PROMPT_VERSION,
            'text' => $text,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        Log::info('knowledge.vision.completed', [
            'file' => basename($path),
            'model' => $model,
            'characters' => mb_strlen($text),
        ]);

        return $text;
    }

    private function responseText(array $response): string
    {
        $parts = [];
        foreach ($response['output'] ?? [] as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    $parts[] = $content['text'];
                }
            }
        }
        return trim(implode("\n\n", $parts));
    }

    private function cachePath(string $key): string
    {
        return storage_path('app/knowledge-vision-cache/'.substr($key, 0, 2).'/'.$key.'.json');
    }
}
