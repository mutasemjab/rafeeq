<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentSettingsController extends Controller
{
    public function update(Request $request, PaymentSettingsService $paymentSettings): RedirectResponse
    {
        $data = $request->validate([
            'mobile_payments_enabled' => 'required|boolean',
        ]);

        $paymentSettings->updateMobilePaymentsEnabled((bool) $data['mobile_payments_enabled']);

        return back()->with('success', 'Mobile payment setting updated successfully.');
    }
}
