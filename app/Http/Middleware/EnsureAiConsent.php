<?php

namespace App\Http\Middleware;

use App\Services\Privacy\AiConsentService;
use Closure;
use Illuminate\Http\Request;

class EnsureAiConsent
{
    public function __construct(private AiConsentService $consentService)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user === null || $user->hasAiConsent()) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => $this->consentService->requiredMessage(),
        ], 403);
    }
}
