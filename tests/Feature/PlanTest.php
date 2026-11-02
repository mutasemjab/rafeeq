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

    public function test_pro_catalog_has_the_monthly_and_annual_usd_prices(): void
    {
        $this->seed(PlanSeeder::class);
        $plans = collect($this->getJson('/api/v1/plans')->assertOk()->json())->keyBy('slug');
        $this->assertSame(10.0, (float) $plans['pro-monthly']['price']);
        $this->assertSame(100.0, (float) $plans['pro-yearly']['price']);
        $this->assertSame('USD', $plans['pro-yearly']['currency']);
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
