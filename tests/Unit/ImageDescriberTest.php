<?php

namespace Tests\Unit;

use App\Services\Documents\ImageDescriber;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImageDescriberTest extends TestCase
{
    public function test_visual_only_images_use_the_responses_api(): void
    {
        Config::set('ai.openai_api_key', 'test-key');
        Config::set('ai.document_vision_model', 'test-vision-'.bin2hex(random_bytes(4)));
        Config::set('ai.document_vision_detail', 'low');
        $path = sys_get_temp_dir().'/rafeeq-image-'.bin2hex(random_bytes(6)).'.jpg';
        file_put_contents($path, base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABB//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPxB//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPxB//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxB//9k='
        ));

        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'A child-friendly sequencing picture showing an action.',
                    ]],
                ]],
            ]),
        ]);

        try {
            $text = app(ImageDescriber::class)->describe($path);
            $this->assertStringContainsString('sequencing picture', $text);
            Http::assertSent(fn(Request $request): bool =>
                $request->url() === 'https://api.openai.com/v1/responses'
                && $request['input'][0]['content'][1]['type'] === 'input_image'
                && str_starts_with($request['input'][0]['content'][1]['image_url'], 'data:image/jpeg;base64,')
            );
        } finally {
            @unlink($path);
        }
    }
}
