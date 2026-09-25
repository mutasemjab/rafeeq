<?php

namespace Tests\Feature;

use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_list_active_plans(): void
    {
        Plan::factory()->count(3)->create(['is_active' => true]);
        Plan::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/plans')->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_free_plan_allows_one_hundred_ai_messages_per_day(): void
    {
        $this->seed(PlanSeeder::class);

        $this->assertDatabaseHas('plans', [
            'slug' => 'free',
            'ai_messages_per_day' => 100,
        ]);
    }
}
