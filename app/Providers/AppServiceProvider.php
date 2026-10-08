<?php

namespace App\Providers;

use App\Repositories\Contracts\VectorSearchRepositoryInterface;
use App\Repositories\MysqlVectorSearchRepository;
use App\Services\AI\AiProviderManager;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\AI\SupportPathwayRegistry;
use App\Services\Search\Contracts\WebSearchServiceInterface;
use App\Services\Search\Providers\BraveSearchProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LlmProviderInterface::class, function ($app) {
            return $app->make(AiProviderManager::class)->driver();
        });

        $this->app->singleton(VectorSearchRepositoryInterface::class, MysqlVectorSearchRepository::class);

        $this->app->singleton(WebSearchServiceInterface::class, BraveSearchProvider::class);
        $this->app->singleton(SupportPathwayRegistry::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrap();
    }
}
