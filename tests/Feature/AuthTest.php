<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Plan::factory()->create(['type' => 'free', 'slug' => 'free', 'is_active' => true, 'ai_messages_per_day' => 5]);
        $this->artisan('passport:install', ['--no-interaction' => true])->assertExitCode(0);
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

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123'), 'status' => 'inactive']);

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
}
