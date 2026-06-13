<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\AccountDeletionService;
use App\Services\Privacy\AiConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserPrivacyController extends Controller
{
    public function consent(Request $request, AiConsentService $consentService): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $consentService->snapshot($request->user()),
        ]);
    }

    public function saveConsent(Request $request, AiConsentService $consentService): JsonResponse
    {
        $data = $request->validate([
            'hasAiConsent' => 'required|boolean',
            'version' => 'nullable|string|max:32|required_if:hasAiConsent,1',
        ]);

        $snapshot = $consentService->save(
            $request->user(),
            (bool) $data['hasAiConsent'],
            $data['version'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => $data['hasAiConsent']
                ? 'AI data-sharing consent saved successfully'
                : 'AI data-sharing consent cleared successfully',
            'data' => $snapshot,
        ]);
    }

    public function deleteAccount(Request $request, AccountDeletionService $accountDeletionService): JsonResponse
    {
        $accountDeletionService->delete($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully',
        ]);
    }
}
