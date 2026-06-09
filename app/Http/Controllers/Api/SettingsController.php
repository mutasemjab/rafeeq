<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentSettingsService;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function index(PaymentSettingsService $paymentSettings): JsonResponse
    {
        return response()->json([
            'payments' => $paymentSettings->snapshot(),
        ]);
    }
}
