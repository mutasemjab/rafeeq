<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|unique:users,email',
            'phone'      => 'nullable|string|max:20',
            'password'   => 'required|string|min:8|confirmed',
            'preferred_language' => 'nullable|in:en,ar',
        ]);

        $user = User::create([
            'name'       => $data['first_name'] . ' ' . $data['last_name'],
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email'      => $data['email'],
            'phone'      => $data['phone'] ?? null,
            'password'   => Hash::make($data['password']),
            'role'       => 'user',
            'status'     => 'active',
            'preferred_language' => $data['preferred_language'] ?? 'ar',
        ]);

        // Assign free plan
        $freePlan = Plan::where('type', 'free')->where('is_active', true)->first();
        if ($freePlan) {
            Subscription::create([
                'user_id'  => $user->id,
                'plan_id'  => $freePlan->id,
                'status'   => 'active',
                'starts_at'=> now(),
            ]);
        }

        $token = $user->createToken('user-token', ['*'])->accessToken;

        return response()->json([
            'message' => 'Registration successful.',
            'token'   => $token,
            'user'    => $this->userArray($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Account is not active.'], 403);
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('user-token', ['*'])->accessToken;

        return response()->json([
            'message' => 'Login successful.',
            'token'   => $token,
            'user'    => $this->userArray($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->activeSubscription();

        return response()->json([
            'user'         => $this->userArray($user),
            'subscription' => $subscription ? [
                'plan'     => $subscription->plan->name ?? null,
                'status'   => $subscription->status,
                'ends_at'  => $subscription->ends_at?->toISOString(),
            ] : null,
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'first_name'         => 'sometimes|string|max:100',
            'last_name'          => 'sometimes|string|max:100',
            'phone'              => 'nullable|string|max:20',
            'preferred_language' => 'nullable|in:en,ar',
            'theme_preference'   => 'nullable|in:light,dark,system',
        ]);

        if (isset($data['first_name']) || isset($data['last_name'])) {
            $first = $data['first_name'] ?? $user->first_name;
            $last  = $data['last_name']  ?? $user->last_name;
            $data['name'] = trim($first . ' ' . $last);
        }

        $user->update($data);

        return response()->json(['message' => 'Profile updated.', 'user' => $this->userArray($user->fresh())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->token()->revoke();
        return response()->json(['message' => 'Logged out.']);
    }

    private function userArray(User $user): array
    {
        return [
            'id'                 => $user->id,
            'name'               => $user->name,
            'first_name'         => $user->first_name,
            'last_name'          => $user->last_name,
            'email'              => $user->email,
            'phone'              => $user->phone,
            'avatar'             => $user->avatar ? asset('storage/' . $user->avatar) : null,
            'role'               => $user->role,
            'status'             => $user->status,
            'preferred_language' => $user->preferred_language,
            'theme_preference'   => $user->theme_preference,
        ];
    }
}
