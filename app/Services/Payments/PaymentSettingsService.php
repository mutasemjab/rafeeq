<?php

namespace App\Services\Payments;

use App\Models\AppSetting;

class PaymentSettingsService
{
    private const MOBILE_PAYMENTS_ENABLED_KEY = 'mobile_payments_enabled';

    public function mobilePaymentsEnabled(): bool
    {
        return $this->boolean(
            self::MOBILE_PAYMENTS_ENABLED_KEY,
            (bool) config('payments.mobile_enabled', true)
        );
    }

    public function payForLaterEnabled(): bool
    {
        return (bool) config('payments.pay_for_later_enabled', false);
    }

    public function availableAppointmentMethods(): array
    {
        if (! $this->mobilePaymentsEnabled()) {
            return [];
        }

        $methods = ['card'];

        if ($this->payForLaterEnabled()) {
            $methods[] = 'pay_for_later';
        }

        return $methods;
    }

    public function updateMobilePaymentsEnabled(bool $enabled): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => self::MOBILE_PAYMENTS_ENABLED_KEY],
            ['value' => $enabled ? '1' : '0']
        );
    }

    public function snapshot(): array
    {
        return [
            'mobile_enabled' => $this->mobilePaymentsEnabled(),
            'pay_for_later_enabled' => $this->payForLaterEnabled(),
            'available_methods' => $this->availableAppointmentMethods(),
        ];
    }

    private function boolean(string $key, bool $default): bool
    {
        $value = AppSetting::query()
            ->where('key', $key)
            ->value('value');

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
