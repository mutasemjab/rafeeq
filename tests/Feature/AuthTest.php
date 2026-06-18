<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Auth\SocialIdentityVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Plan::factory()->create(['type' => 'free', 'slug' => 'free', 'is_active' => true, 'ai_messages_per_day' => 5]);
        $this->setUpPassport();
    }

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'first_name'            => 'Ali',
            'last_name'             => 'Hassan',
            'email'                 => 'ali@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['token', 'user']);

        $this->assertDatabaseHas('users', ['email' => 'ali@example.com']);
        $this->assertDatabaseHas('subscriptions', ['status' => 'active']);
    }

    public function test_registration_requires_valid_email(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Ali',
            'last_name'  => 'Hassan',
            'email'      => 'not-an-email',
            'password'   => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123'), 'status' => 'active']);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'secret123',
        ])->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_user_can_login_with_lowercase_email_when_legacy_record_uses_mixed_case(): void
    {
        $user = User::factory()->create([
            'email' => 'legacy.user@example.com',
            'password' => bcrypt('secret123'),
            'status' => 'active',
        ]);

        DB::table('users')
            ->where('id', $user->id)
            ->update(['email' => 'Legacy.User@Example.COM']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'legacy.user@example.com',
            'password' => 'secret123',
        ])->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123'), 'status' => 'suspended']);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'secret123',
        ])->assertStatus(403);
    }

    public function test_wrong_password_returns_422(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct')]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'user-api')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_user_can_login_with_google_social_and_new_account_is_created(): void
    {
        $this->mock(SocialIdentityVerifier::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verify')
                ->once()
                ->with('google', 'google-token')
                ->andReturn([
                    'provider_user_id' => 'google-user-1',
                    'email' => 'social@example.com',
                    'email_verified' => true,
                    'name' => 'Sara Ahmed',
                    'first_name' => 'Sara',
                    'last_name' => 'Ahmed',
                    'avatar' => null,
                    'provider_data' => ['sub' => 'google-user-1'],
                ]);
        });

        $this->postJson('/api/v1/auth/social', [
            'provider' => 'google',
            'id_token' => 'google-token',
            'preferred_language' => 'en',
        ])
            ->assertOk()
            ->assertJsonPath('provider', 'google')
            ->assertJsonPath('is_new_user', true)
            ->assertJsonPath('user.email', 'social@example.com')
            ->assertJsonStructure(['token', 'user']);

        $this->assertDatabaseHas('users', [
            'email' => 'social@example.com',
            'first_name' => 'Sara',
            'last_name' => 'Ahmed',
            'preferred_language' => 'en',
        ]);
        $this->assertDatabaseHas('social_accounts', [
            'provider' => 'google',
            'provider_user_id' => 'google-user-1',
            'provider_email' => 'social@example.com',
        ]);
        $this->assertDatabaseHas('subscriptions', ['status' => 'active']);
    }

    public function test_social_login_links_existing_user_even_if_stored_email_case_differs(): void
    {
        $user = User::factory()->create([
            'email' => 'mixedcase@example.com',
            'status' => 'active',
        ]);

        DB::table('users')
            ->where('id', $user->id)
            ->update(['email' => 'MixedCase@Example.COM']);

        $this->mock(SocialIdentityVerifier::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verify')
                ->once()
                ->with('google', 'mixed-case-google-token')
                ->andReturn([
                    'provider_user_id' => 'google-user-mixed-case',
                    'email' => 'mixedcase@example.com',
                    'email_verified' => true,
                    'name' => 'Mixed Case',
                    'first_name' => 'Mixed',
                    'last_name' => 'Case',
                    'avatar' => null,
                    'provider_data' => ['sub' => 'google-user-mixed-case'],
                ]);
        });

        $this->postJson('/api/v1/auth/social', [
            'provider' => 'google',
            'id_token' => 'mixed-case-google-token',
        ])
            ->assertOk()
            ->assertJsonPath('is_new_user', false)
            ->assertJsonPath('user.id', $user->id);

        $this->assertSame(1, User::query()->whereRaw('LOWER(email) = ?', ['mixedcase@example.com'])->count());
    }

    public function test_social_login_links_existing_user_by_email(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@example.com',
            'status' => 'active',
        ]);

        $this->mock(SocialIdentityVerifier::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verify')
                ->once()
                ->with('google', 'existing-google-token')
                ->andReturn([
                    'provider_user_id' => 'google-user-2',
                    'email' => 'existing@example.com',
                    'email_verified' => true,
                    'name' => 'Existing User',
                    'first_name' => 'Existing',
                    'last_name' => 'User',
                    'avatar' => null,
                    'provider_data' => ['sub' => 'google-user-2'],
                ]);
        });

        $this->postJson('/api/v1/auth/social', [
            'provider' => 'google',
            'id_token' => 'existing-google-token',
        ])
            ->assertOk()
            ->assertJsonPath('is_new_user', false)
            ->assertJsonPath('user.id', $user->id);

        $this->assertSame(1, User::query()->where('email', 'existing@example.com')->count());
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-user-2',
        ]);
    }

    public function test_user_can_login_with_existing_apple_social_account_without_email_claim(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'apple',
            'provider_user_id' => 'apple-user-1',
            'provider_email' => null,
            'provider_data' => ['sub' => 'apple-user-1'],
        ]);

        $this->mock(SocialIdentityVerifier::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verify')
                ->once()
                ->with('apple', 'apple-token')
                ->andReturn([
                    'provider_user_id' => 'apple-user-1',
                    'email' => null,
                    'email_verified' => false,
                    'name' => null,
                    'first_name' => null,
                    'last_name' => null,
                    'avatar' => null,
                    'provider_data' => ['sub' => 'apple-user-1'],
                ]);
        });

        $this->postJson('/api/v1/auth/social', [
            'provider' => 'apple',
            'id_token' => 'apple-token',
        ])
            ->assertOk()
            ->assertJsonPath('provider', 'apple')
            ->assertJsonPath('is_new_user', false)
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_suspended_user_cannot_login_with_social_provider(): void
    {
        $user = User::factory()->create([
            'status' => 'suspended',
        ]);

        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'apple',
            'provider_user_id' => 'apple-user-2',
            'provider_email' => $user->email,
            'provider_data' => ['sub' => 'apple-user-2'],
        ]);

        $this->mock(SocialIdentityVerifier::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verify')
                ->once()
                ->with('apple', 'suspended-apple-token')
                ->andReturn([
                    'provider_user_id' => 'apple-user-2',
                    'email' => 'suspended@example.com',
                    'email_verified' => true,
                    'name' => null,
                    'first_name' => null,
                    'last_name' => null,
                    'avatar' => null,
                    'provider_data' => ['sub' => 'apple-user-2'],
                ]);
        });

        $this->postJson('/api/v1/auth/social', [
            'provider' => 'apple',
            'id_token' => 'suspended-apple-token',
        ])->assertStatus(403);
    }
}
