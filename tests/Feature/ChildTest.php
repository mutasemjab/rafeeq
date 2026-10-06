<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChildTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user      = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    public function test_birth_date_and_age_round_trip_on_create_edit_and_list(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 6));
        $created = $this->actingAs($this->user, 'user-api')->postJson('/api/v1/children', [
            'name' => 'Test case', 'date_of_birth' => '2019-10-07', 'gender' => 'female',
            'country_of_birth' => 'Egypt', 'country_of_residence' => 'UAE',
        ])->assertCreated()->assertJsonPath('age', 6)->assertJsonPath('date_of_birth', '2019-10-07')
            ->assertJsonPath('country_of_birth', 'Egypt')->assertJsonPath('country_of_residence', 'UAE');
        $id = $created->json('id');
        // Same form encoding/method override used by the mobile multipart client.
        $this->post("/api/v1/children/{$id}", [
            '_method' => 'PUT', 'date_of_birth' => '2019-10-06',
            'country_of_residence' => '', 'notes' => '',
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('age', 7)
            ->assertJsonPath('country_of_residence', null);
        $this->getJson("/api/v1/children/{$id}")->assertOk()
            ->assertJsonPath('date_of_birth', '2019-10-06')->assertJsonPath('age', 7);
        $this->getJson('/api/v1/children')->assertOk()->assertJsonPath('0.age', 7);
        $this->assertDatabaseHas('children', ['id' => $id, 'country_of_birth' => 'Egypt', 'country_of_residence' => null]);
    }

    public function test_newborn_is_supported_but_future_birth_dates_are_rejected(): void
    {
        $this->actingAs($this->user, 'user-api')->postJson('/api/v1/children', [
            'name' => 'Newborn', 'date_of_birth' => today()->toDateString(), 'gender' => 'male',
        ])->assertCreated()->assertJsonPath('age', 0);
        $this->postJson('/api/v1/children', [
            'name' => 'Future', 'date_of_birth' => today()->addDay()->toDateString(), 'gender' => 'male',
        ])->assertUnprocessable()->assertJsonValidationErrors('date_of_birth');
    }

    public function test_user_can_create_child(): void
    {
        $this->actingAs($this->user, 'user-api')
            ->postJson('/api/v1/children', [
                'name'          => 'Yousef',
                'date_of_birth' => '2019-05-10',
                'gender'        => 'male',
            ])
            ->assertStatus(201)
            ->assertJsonPath('name', 'Yousef');

        $this->assertDatabaseHas('children', ['name' => 'Yousef', 'user_id' => $this->user->id]);
    }

    public function test_user_cannot_access_another_users_child(): void
    {
        $child = Child::factory()->create(['user_id' => $this->otherUser->id]);

        $this->actingAs($this->user, 'user-api')
            ->getJson("/api/v1/children/{$child->id}")
            ->assertStatus(403);
    }

    public function test_user_cannot_delete_another_users_child(): void
    {
        $child = Child::factory()->create(['user_id' => $this->otherUser->id]);

        $this->actingAs($this->user, 'user-api')
            ->deleteJson("/api/v1/children/{$child->id}")
            ->assertStatus(403);
    }

    public function test_user_can_list_own_children(): void
    {
        Child::factory()->count(3)->create(['user_id' => $this->user->id]);
        Child::factory()->count(2)->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user, 'user-api')
            ->getJson('/api/v1/children')
            ->assertOk();

        $this->assertCount(3, $response->json());
    }

    public function test_user_can_update_own_child(): void
    {
        $child = Child::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user, 'user-api')
            ->putJson("/api/v1/children/{$child->id}", ['name' => 'Updated Name'])
            ->assertOk()
            ->assertJsonPath('name', 'Updated Name');
    }

    public function test_user_cannot_update_another_users_child(): void
    {
        $child = Child::factory()->create(['user_id' => $this->otherUser->id]);

        $this->actingAs($this->user, 'user-api')
            ->putJson("/api/v1/children/{$child->id}", ['name' => 'Hack'])
            ->assertStatus(403);
    }

    public function test_child_requires_name_and_dob(): void
    {
        $this->actingAs($this->user, 'user-api')
            ->postJson('/api/v1/children', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'date_of_birth', 'gender']);
    }
}
