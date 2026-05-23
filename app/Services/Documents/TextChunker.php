<?php

namespace App\Services\Documents;

class TextChunker
{
    public function chunk(string $text, array $options = []): array
    {
        $chunkSize = $options['chunk_size'] ?? 1000;
        $overlap   = $options['overlap']    ?? 150;

        $text  = preg_replace('/\s+/', ' ', trim($text));
        $words = explode(' ', $text);
        $words = array_values(array_filter($words, fn($w) => $w !== ''));

        if (empty($words)) {
            return [];
        }

        $chunks = [];
        $start  = 0;
        $total  = count($words);

        while ($start < $total) {
            $end   = min($start + $chunkSize, $total);
            $slice = array_slice($words, $start, $end - $start);
            $content = implode(' ', $slice);

            if (!empty(trim($content))) {
                $chunks[] = [
                    'content'    => $content,
                    'word_count' => count($slice),
                ];
            }

            if ($end >= $total) break;
            $start = $end - $overlap;
            if ($start < 0) $start = 0;
        }

        return $chunks;
    }
}
