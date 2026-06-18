<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SocialAccount;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Auth\SocialIdentityVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'email' => $this->normalizeEmail($request->input('email')),
        ]);

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

        $this->assignFreePlan($user);

        $token = $user->createToken('user-token', ['*'])->accessToken;

        return response()->json([
            'message' => 'Registration successful.',
            'token'   => $token,
            'user'    => $this->userArray($user),
        ], 201);
    }

    public function socialLogin(Request $request, SocialIdentityVerifier $verifier): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', Rule::in(['google', 'apple'])],
            'id_token' => 'required|string',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'preferred_language' => 'nullable|in:en,ar',
        ]);

        $identity = $verifier->verify($data['provider'], $data['id_token']);
        [$user, $isNewUser] = $this->resolveSocialUser($data['provider'], $identity, $data);

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Account is not active.'], 403);
        }

        $this->syncSocialAccount($user, $data['provider'], $identity);
        $this->syncVerifiedEmail($user, $identity);
        $this->syncMissingProfileFields($user, $identity, $data);

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('user-token', ['*'])->accessToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'provider' => $data['provider'],
            'is_new_user' => $isNewUser,
            'user' => $this->userArray($user->fresh()),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $request->merge([
            'email' => $this->normalizeEmail($request->input('email')),
        ]);

        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = $this->findUserByEmail($data['email']);

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

    private function resolveSocialUser(string $provider, array $identity, array $requestData): array
    {
        $socialAccount = SocialAccount::query()
            ->with('user')
            ->where('provider', $provider)
            ->where('provider_user_id', $identity['provider_user_id'])
            ->first();

        if ($socialAccount) {
            return [$socialAccount->user, false];
        }

        if (! empty($identity['email'])) {
            $user = $this->findUserByEmail($identity['email']);

            if ($user) {
                return [$user, false];
            }
        }

        return [$this->createSocialUser($provider, $identity, $requestData), true];
    }

    private function createSocialUser(string $provider, array $identity, array $requestData): User
    {
        [$firstName, $lastName] = $this->resolveNameParts($identity, $requestData);
        $displayName = $this->makeDisplayName($firstName, $lastName, $identity['name'] ?? null, ucfirst($provider) . ' User');
        $email = $identity['email'] ?? $this->placeholderSocialEmail($provider, $identity['provider_user_id']);

        $user = User::create([
            'name' => $displayName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $this->normalizeEmail($email),
            'password' => Hash::make(Str::random(40)),
            'role' => 'user',
            'status' => 'active',
            'preferred_language' => $requestData['preferred_language'] ?? 'ar',
        ]);

        if (! empty($identity['email']) && ! empty($identity['email_verified'])) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $this->assignFreePlan($user);

        return $user;
    }

    private function syncSocialAccount(User $user, string $provider, array $identity): void
    {
        SocialAccount::query()->updateOrCreate(
            [
                'provider' => $provider,
                'provider_user_id' => $identity['provider_user_id'],
            ],
            [
                'user_id' => $user->id,
                'provider_email' => $identity['email'] ?? null,
                'provider_data' => $identity['provider_data'] ?? [],
            ]
        );
    }

    private function findUserByEmail(?string $email): ?User
    {
        $normalizedEmail = $this->normalizeEmail($email);

        if (! $normalizedEmail) {
            return null;
        }

        return User::query()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();
    }

    private function normalizeEmail(?string $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        return mb_strtolower(trim($email));
    }

    private function syncVerifiedEmail(User $user, array $identity): void
    {
        if (
            ! empty($identity['email'])
            && ! empty($identity['email_verified'])
            && strtolower((string) $user->email) === strtolower((string) $identity['email'])
            && $user->email_verified_at === null
        ) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }
    }

    private function syncMissingProfileFields(User $user, array $identity, array $requestData): void
    {
        [$firstName, $lastName] = $this->resolveNameParts($identity, $requestData);
        $updates = [];

        if (! $user->first_name && $firstName) {
            $updates['first_name'] = $firstName;
        }

        if (! $user->last_name && $lastName) {
            $updates['last_name'] = $lastName;
        }

        if (
            (! $user->name || trim($user->name) === '')
            && (($updates['first_name'] ?? $user->first_name) || ($updates['last_name'] ?? $user->last_name) || ($identity['name'] ?? null))
        ) {
            $updates['name'] = $this->makeDisplayName(
                $updates['first_name'] ?? $user->first_name,
                $updates['last_name'] ?? $user->last_name,
                $identity['name'] ?? null,
                'User'
            );
        }

        if (! $user->preferred_language && ! empty($requestData['preferred_language'])) {
            $updates['preferred_language'] = $requestData['preferred_language'];
        }

        if ($updates !== []) {
            $user->update($updates);
        }
    }

    private function assignFreePlan(User $user): void
    {
        $freePlan = Plan::where('type', 'free')->where('is_active', true)->first();

        if (! $freePlan) {
            return;
        }

        Subscription::create([
            'user_id'  => $user->id,
            'plan_id'  => $freePlan->id,
            'status'   => 'active',
            'starts_at'=> now(),
        ]);
    }

    private function resolveNameParts(array $identity, array $requestData): array
    {
        $firstName = isset($requestData['first_name']) ? trim((string) $requestData['first_name']) : trim((string) ($identity['first_name'] ?? ''));
        $lastName = isset($requestData['last_name']) ? trim((string) $requestData['last_name']) : trim((string) ($identity['last_name'] ?? ''));

        if (($firstName === '' || $lastName === '') && ! empty($identity['name'])) {
            $parts = preg_split('/\s+/', trim((string) $identity['name'])) ?: [];

            if ($firstName === '' && isset($parts[0])) {
                $firstName = $parts[0];
            }

            if ($lastName === '' && count($parts) > 1) {
                $lastName = implode(' ', array_slice($parts, 1));
            }
        }

        return [$firstName !== '' ? $firstName : null, $lastName !== '' ? $lastName : null];
    }

    private function makeDisplayName(?string $firstName, ?string $lastName, ?string $fallbackName, string $default): string
    {
        $fullName = trim(implode(' ', array_filter([$firstName, $lastName])));

        if ($fullName !== '') {
            return $fullName;
        }

        if ($fallbackName && trim($fallbackName) !== '') {
            return trim($fallbackName);
        }

        return $default;
    }

    private function placeholderSocialEmail(string $provider, string $providerUserId): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9]+/', '-', $providerUserId) ?: Str::random(12);

        return strtolower($provider . '-' . trim($slug, '-') . '@social.rafeeq.local');
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
