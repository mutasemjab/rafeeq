<?php

namespace App\Services\Search;

class PackedEmbedding
{
    /** Unit-length little-endian float32 vectors avoid parsing large JSON on every search. */
    public static function encode(?string $json, int $dimensions): ?string
    {
        $values = json_decode($json ?? '', true);
        if (! is_array($values) || $dimensions < 1 || count($values) !== $dimensions) return null;
        $norm = 0.0;
        foreach ($values as $value) {
            if (! is_numeric($value) || ! is_finite((float) $value)) return null;
            $norm += (float) $value * (float) $value;
        }
        if ($norm <= 0 || ! is_finite($norm)) return null;
        $norm = sqrt($norm);
        return pack('g*', ...array_map(fn ($value) => (float) $value / $norm, array_values($values)));
    }

    public static function decode(mixed $binary, int $dimensions): ?array
    {
        if (is_resource($binary)) $binary = stream_get_contents($binary);
        if (! is_string($binary) || $dimensions < 1 || strlen($binary) !== $dimensions * 4) return null;
        $values = array_values(unpack('g*', $binary));
        foreach ($values as $value) if (! is_finite($value)) return null;
        return $values;
    }
}
