<?php

namespace Tests\Unit;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use Tests\TestCase;

class MessageResourcePrivacyTest extends TestCase
{
    public function test_historical_private_file_urls_are_removed_without_hiding_public_references(): void
    {
        $message = new Message(['role' => 'assistant', 'content' => 'Summary', 'sources' => [
            ['source_type' => 'chat_attachment', 'attachment_id' => 12, 'title' => 'Report', 'url' => 'https://old.example/storage/chat-attachments/1/2/report.pdf'],
            ['source_type' => 'web', 'url' => 'https://app.example/storage/children/1/documents/report.pdf'],
            ['source_type' => 'web', 'url' => 'https://www.nhs.uk/conditions/autism/'],
        ]]);
        $sources = (new MessageResource($message))->toArray(request())['sources'];
        $this->assertArrayNotHasKey('url', $sources[0]);
        $this->assertSame(12, $sources[0]['attachment_id']);
        $this->assertArrayNotHasKey('url', $sources[1]);
        $this->assertSame('https://www.nhs.uk/conditions/autism/', $sources[2]['url']);
    }
}
