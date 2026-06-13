<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentSettingsService;
use App\Services\Privacy\PrivacySettingsService;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function index(
        PaymentSettingsService $paymentSettings,
        PrivacySettingsService $privacySettings,
    ): JsonResponse
    {
        return response()->json([
            'payments' => $paymentSettings->snapshot(),
            'privacy' => $privacySettings->snapshot(),
        ]);
    }
}
