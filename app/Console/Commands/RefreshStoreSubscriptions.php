<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\Payments\StoreSubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshStoreSubscriptions extends Command
{
    protected $signature = 'subscriptions:refresh-stores';

    protected $description = 'Reconcile verified store subscriptions, renewals and refunds.';

    public function handle(StoreSubscriptionService $stores): int
    {
        $failed = false;
        Subscription::whereNotNull('store_purchase_key')->with('user')
            ->whereIn('status', ['active', 'expired'])
            ->where(fn ($q) => $q->where('status', 'active')->orWhere('ends_at', '>', now()->subDays(60)))
            ->chunkById(100, function ($subscriptions) use ($stores, &$failed) {
                foreach ($subscriptions as $sub) {
                    try {
                        $stores->synchronize($sub->user, $sub->provider, $sub->store_reference);
                    } catch (\Throwable $e) {
                        $failed = true;
                        Log::warning('Store subscription refresh deferred', ['subscription_id' => $sub->id, 'error' => get_class($e)]);
                    }
                }
            });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
