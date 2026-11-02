<?php

namespace App\Services\Payments;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreSubscriptionService
{
    public function __construct(private StorePurchaseVerifier $verifier, private PaymentSettingsService $settings)
    {
    }

    public function catalog(User $user, string $store): array
    {
        if (! $user->store_account_token) {
            User::whereKey($user->id)->whereNull('store_account_token')->update(['store_account_token' => (string) Str::uuid()]);
            $user->refresh();
        }
        $products = [];
        if ($this->settings->mobilePaymentsEnabled() && $this->verifier->configured($store)) {
            $slugs = Plan::where('type', 'pro')->where('is_active', true)->pluck('slug')->all();
            $products = array_intersect_key(config("store_subscriptions.$store.products"), array_flip($slugs));
        }

        return ['account_token' => $user->store_account_token, 'has_pro' => app(\App\Services\AI\ChatModePolicy::class)->canUseExtraThinking($user), 'products' => (object) $products];
    }

    public function synchronize(User $user, string $store, string $reference): Subscription
    {
        abort_unless($user->store_account_token, 403, 'Missing store account binding.');
        // Serialize verification as well as persistence. A stale store response
        // must not overwrite a more recent refund, renewal or replacement.
        $lock = Cache::lock('store-sync:'.$user->id, 90);
        abort_unless($lock->get(), 409, 'Another verification is in progress. Try again.');
        try {
            $result = $this->verifier->verify($store, $reference, $user->store_account_token);
            $slug = array_search($result['product'], config("store_subscriptions.$store.products"), true);
            $plan = Plan::where('slug', $slug)->where('type', 'pro')->firstOrFail();
            $purchaseKey = hash('sha256', $store.':'.$result['reference']);
            $sub = DB::transaction(function () use ($user, $store, $purchaseKey, $result, $plan) {
                User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $sub = Subscription::where('store_purchase_key', $purchaseKey)->lockForUpdate()->first() ?? new Subscription();
                abort_if($sub->exists && $sub->user_id != $user->id, 403, 'Already linked to a different account.');
                if (data_get($sub->metadata, 'superseded')) {
                    return $sub;
                }
                if (! empty($result['previous'])) {
                    $previous = Subscription::where('store_purchase_key', hash('sha256', $store.':'.$result['previous']))->lockForUpdate()->first();
                    abort_if($previous && $previous->user_id != $user->id, 403, 'Linked purchase belongs to another account.');
                    if ($previous) {
                        $previous->update(['status' => 'canceled', 'ends_at' => now(), 'metadata' => ['superseded' => true]]);
                    }
                }
                $sub->forceFill(['user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => $store,
                    'store_purchase_key' => $purchaseKey, 'store_reference' => $result['reference'],
                    'status' => $result['active'] ? 'active' : 'expired', 'starts_at' => $sub->starts_at ?? now(),
                    'ends_at' => $result['end']])->save();

                return $sub;
            });
            if ($store === 'google' && $sub->status === 'active' && ! empty($result['acknowledge'])) {
                $this->verifier->acknowledgePlay($result['play_product'], $reference);
            }

            return $sub->load('plan');
        } finally {
            $lock->release();
        }
    }
}
