<?php

namespace App\Services\Search\Contracts;

interface WebSearchServiceInterface
{
    public function search(string $query, array $options = []): array;
}
