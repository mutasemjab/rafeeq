<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\ChildMemory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChildMemoryTest extends TestCase
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

    public function test_user_can_list_childs_memories(): void
    {
        $child = Child::factory()->create(['user_id' => $this->user->id]);
        ChildMemory::factory()->count(3)->create(['child_id' => $child->id]);

        $response = $this->actingAs($this->user, 'user-api')
            ->getJson("/api/v1/children/{$child->id}/memories")
            ->assertOk();

        $this->assertCount(3, $response->json());
    }

    public function test_user_cannot_view_another_users_childs_memories(): void
    {
        $child = Child::factory()->create(['user_id' => $this->otherUser->id]);

        $this->actingAs($this->user, 'user-api')
            ->getJson("/api/v1/children/{$child->id}/memories")
            ->assertStatus(403);
    }

    public function test_user_can_delete_own_childs_memory(): void
    {
        $child  = Child::factory()->create(['user_id' => $this->user->id]);
        $memory = ChildMemory::factory()->create(['child_id' => $child->id]);

        $this->actingAs($this->user, 'user-api')
            ->deleteJson("/api/v1/memories/{$memory->id}")
            ->assertOk();

        $this->assertSoftDeleted('child_memories', ['id' => $memory->id]);
    }

    public function test_user_cannot_delete_another_users_childs_memory(): void
    {
        $child  = Child::factory()->create(['user_id' => $this->otherUser->id]);
        $memory = ChildMemory::factory()->create(['child_id' => $child->id]);

        $this->actingAs($this->user, 'user-api')
            ->deleteJson("/api/v1/memories/{$memory->id}")
            ->assertStatus(403);
    }
}
