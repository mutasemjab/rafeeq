<?php

namespace App\Services\Documents;

class TextChunker
{
    /**
     * Build bounded, overlapping chunks while retaining source page hints.
     *
     * @param  string|array<int, array{page?: int|null, text?: string}>  $input
     * @return array<int, array<string, mixed>>
     */
    public function chunk(string|array $input, array $options = []): array
    {
        $pages = is_string($input)
            ? [['page' => 1, 'text' => $input]]
            : $input;

        $targetWords = max(120, (int) ($options['chunk_size'] ?? config('ai.document_chunk_words', 420)));
        $overlapWords = max(0, min(
            (int) ($options['overlap'] ?? config('ai.document_chunk_overlap_words', 60)),
            (int) floor($targetWords / 2)
        ));
        $maxWords = max($targetWords, (int) round($targetWords * 1.2));
        $maxBytes = max(1000, min(
            (int) ($options['max_bytes'] ?? config('ai.document_chunk_max_bytes', 7500)),
            8000
        ));

        $segments = $this->segments($pages, $targetWords, $maxWords, $maxBytes);

        if ($segments === []) {
            return [];
        }

        $chunks = [];
        $current = [];
        $currentWords = 0;
        $currentBytes = 0;

        foreach ($segments as $segment) {
            $separatorBytes = $current === [] ? 0 : 2;
            $wouldOverflow = $current !== [] && (
                $currentWords + $segment['word_count'] > $maxWords ||
                $currentBytes + $separatorBytes + strlen($segment['text']) > $maxBytes
            );

            if ($wouldOverflow) {
                $chunks[] = $this->formatChunk($current, count($chunks));
                $current = $this->overlapSegments($current, $overlapWords);
                $currentWords = array_sum(array_column($current, 'word_count'));
                $currentBytes = strlen(implode("\n\n", array_column($current, 'text')));

                if (
                    $current !== [] &&
                    $currentBytes + 2 + strlen($segment['text']) > $maxBytes
                ) {
                    $current = [];
                    $currentWords = 0;
                    $currentBytes = 0;
                }
            }

            $current[] = $segment;
            $currentWords += $segment['word_count'];
            $currentBytes = strlen(implode("\n\n", array_column($current, 'text')));
        }

        if ($current !== []) {
            $chunks[] = $this->formatChunk($current, count($chunks));
        }

        return $chunks;
    }

    private function segments(array $pages, int $targetWords, int $maxWords, int $maxBytes): array
    {
        $segments = [];

        foreach ($pages as $pageIndex => $page) {
            $pageNumber = isset($page['page']) && $page['page'] !== null
                ? max(1, (int) $page['page'])
                : $pageIndex + 1;
            $text = $this->normalize((string) ($page['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $blocks = preg_split('/\n{2,}/u', $text) ?: [$text];

            foreach ($blocks as $block) {
                foreach ($this->splitBlock(trim($block), $targetWords, $maxWords, $maxBytes) as $piece) {
                    if ($piece === '') {
                        continue;
                    }

                    $segments[] = [
                        'page_number' => $pageNumber,
                        'text' => $piece,
                        'word_count' => $this->wordCount($piece),
                    ];
                }
            }
        }

        return $segments;
    }

    private function splitBlock(string $text, int $targetWords, int $maxWords, int $maxBytes): array
    {
        if ($text === '') {
            return [];
        }

        if ($this->wordCount($text) <= $maxWords && strlen($text) <= $maxBytes) {
            return [$text];
        }

        $sentences = preg_split('/(?<=[\.\!\?؟؛۔])\s+/u', $text) ?: [$text];

        if (count($sentences) <= 1) {
            return $this->splitByWords($text, $targetWords, $maxBytes);
        }

        $pieces = [];
        $current = [];

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);

            if ($sentence === '') {
                continue;
            }

            if ($this->wordCount($sentence) > $maxWords || strlen($sentence) > $maxBytes) {
                if ($current !== []) {
                    $pieces[] = implode(' ', $current);
                    $current = [];
                }

                array_push($pieces, ...$this->splitByWords($sentence, $targetWords, $maxBytes));
                continue;
            }

            $candidate = trim(implode(' ', [...$current, $sentence]));

            if (
                $current !== [] &&
                ($this->wordCount($candidate) > $targetWords || strlen($candidate) > $maxBytes)
            ) {
                $pieces[] = implode(' ', $current);
                $current = [];
            }

            $current[] = $sentence;
        }

        if ($current !== []) {
            $pieces[] = implode(' ', $current);
        }

        return $pieces;
    }

    private function splitByWords(string $text, int $targetWords, int $maxBytes): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $pieces = [];
        $current = [];

        foreach ($words as $word) {
            $parts = strlen($word) > $maxBytes
                ? $this->splitOversizedWord($word, $maxBytes)
                : [$word];

            foreach ($parts as $part) {
                $candidate = trim(implode(' ', [...$current, $part]));

                if ($current !== [] && (count($current) >= $targetWords || strlen($candidate) > $maxBytes)) {
                    $pieces[] = implode(' ', $current);
                    $current = [];
                }

                $current[] = $part;
            }
        }

        if ($current !== []) {
            $pieces[] = implode(' ', $current);
        }

        return $pieces;
    }

    private function splitOversizedWord(string $word, int $maxBytes): array
    {
        $parts = [];
        $remaining = $word;

        while ($remaining !== '') {
            $part = mb_strcut($remaining, 0, $maxBytes, 'UTF-8');

            if ($part === '') {
                break;
            }

            $parts[] = $part;
            $remaining = substr($remaining, strlen($part));
        }

        return $parts;
    }

    private function overlapSegments(array $segments, int $overlapWords): array
    {
        if ($overlapWords <= 0) {
            return [];
        }

        $selected = [];
        $remaining = $overlapWords;

        for ($index = count($segments) - 1; $index >= 0 && $remaining > 0; $index--) {
            $segment = $segments[$index];

            if ($segment['word_count'] <= $remaining) {
                array_unshift($selected, $segment);
                $remaining -= $segment['word_count'];
                continue;
            }

            $words = preg_split('/\s+/u', trim($segment['text'])) ?: [];
            $tail = implode(' ', array_slice($words, -$remaining));

            if ($tail !== '') {
                array_unshift($selected, [
                    'page_number' => $segment['page_number'],
                    'text' => $tail,
                    'word_count' => $this->wordCount($tail),
                ]);
            }

            break;
        }

        return $selected;
    }

    private function formatChunk(array $segments, int $index): array
    {
        $content = $this->normalize(implode("\n\n", array_column($segments, 'text')));
        $first = $segments[0];
        $last = $segments[array_key_last($segments)];

        return [
            'chunk_index' => $index,
            'page_number' => $first['page_number'],
            'end_page_number' => $last['page_number'],
            'content' => $content,
            'word_count' => $this->wordCount($content),
        ];
    }

    private function wordCount(string $text): int
    {
        return count(array_filter(
            preg_split('/\s+/u', trim($text)) ?: [],
            fn(string $word): bool => $word !== ''
        ));
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $text);
        $text = preg_replace('/[\t ]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
