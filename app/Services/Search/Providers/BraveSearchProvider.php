<?php

namespace App\Services\Search\Providers;

use App\Services\Search\Contracts\WebSearchServiceInterface;
use Illuminate\Support\Facades\Http;
use Throwable;

class BraveSearchProvider implements WebSearchServiceInterface
{
    private const BRAVE_SEARCH_URL = 'https://api.search.brave.com/res/v1/web/search';

    /**
     * Perform a web search via the Brave Search API.
     *
     * Returns an empty array when:
     *  - web search is disabled in config (ai.web_search_enabled = false)
     *  - the API key is not configured
     *  - the request fails
     *
     * @param  string  $query
     * @param  array   $options  Optional query parameters (e.g. count, offset, lang).
     * @return array   Each result has keys: source_label, source_type, title, url, snippet.
     */
    public function search(string $query, array $options = []): array
    {
        if (!config('ai.web_search_enabled', false)) {
            return [];
        }

        $apiKey = config('ai.brave_search_api_key');

        if (empty($apiKey)) {
            return [];
        }

        try {
            $queryParams = array_merge(['q' => $query], $options);

            $response = Http::withHeaders([
                'Accept'                => 'application/json',
                'Accept-Encoding'       => 'gzip',
                'X-Subscription-Token'  => $apiKey,
            ])->get(self::BRAVE_SEARCH_URL, $queryParams);

            if (!$response->successful()) {
                return [];
            }

            $data    = $response->json();
            $webData = $data['web']['results'] ?? [];

            $results = [];

            foreach ($webData as $index => $item) {
                $results[] = [
                    'source_label' => 'WEB_SOURCE_' . ($index + 1),
                    'source_type'  => 'web',
                    'title'        => $item['title'] ?? '',
                    'url'          => $item['url'] ?? '',
                    'snippet'      => $item['description'] ?? '',
                ];
            }

            return $results;
        } catch (Throwable $e) {
            // Fail silently — web search is best-effort.
            return [];
        }
    }
}
