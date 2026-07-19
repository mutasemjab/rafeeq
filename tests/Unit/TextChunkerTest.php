<?php

namespace Tests\Unit;

use App\Services\Documents\TextChunker;
use Tests\TestCase;

class TextChunkerTest extends TestCase
{
    public function test_chunks_are_bounded_and_keep_page_hints(): void
    {
        $pages = [
            ['page' => 3, 'text' => str_repeat('Arabic language assessment sentence. ', 100)],
            ['page' => 4, 'text' => str_repeat('Speech therapy intervention sentence. ', 100)],
        ];

        $chunks = (new TextChunker())->chunk($pages, [
            'chunk_size' => 120,
            'overlap' => 20,
            'max_bytes' => 1800,
        ]);

        $this->assertGreaterThan(1, count($chunks));
        $this->assertSame(3, $chunks[0]['page_number']);
        $this->assertSame(4, $chunks[array_key_last($chunks)]['end_page_number']);
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(1800, strlen($chunk['content']));
        }
    }
}
