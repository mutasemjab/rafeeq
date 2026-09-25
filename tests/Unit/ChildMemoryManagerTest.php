<?php

namespace Tests\Unit;

use App\Models\Child;
use App\Models\ChildMemory;
use App\Models\User;
use App\Services\AI\ChildMemoryManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ChildMemoryManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_only_high_confidence_caregiver_facts_and_updates_by_stable_key(): void
    {
        Config::set('ai.memory_minimum_confidence', 0.78);
        $user = User::factory()->create();
        $child = Child::factory()->create(['user_id' => $user->id]);
        $manager = new ChildMemoryManager();

        $saved = $manager->applyCandidates($child->id, $user->id, null, [
            [
                'key' => 'communication.primary_language',
                'type' => 'language',
                'title' => 'Primary language',
                'content' => 'The child primarily speaks Arabic.',
                'confidence' => 0.98,
                'evidence' => 'لغته الأساسية العربية',
                'fact_status' => 'confirmed_by_caregiver',
            ],
            [
                'key' => 'diagnosis.inferred',
                'type' => 'diagnosis',
                'title' => 'Possible diagnosis',
                'content' => 'The child may have a diagnosis.',
                'confidence' => 0.4,
                'evidence' => 'أنا قلقة',
                'fact_status' => 'reported_concern',
            ],
        ]);

        $this->assertSame(1, $saved);
        $this->assertDatabaseCount('child_memories', 1);

        $manager->applyCandidates($child->id, $user->id, null, [[
            'key' => 'communication.primary_language',
            'type' => 'language',
            'title' => 'Primary language',
            'content' => 'The child uses Arabic and English.',
            'confidence' => 0.99,
            'evidence' => 'يستخدم العربية والإنجليزية',
            'fact_status' => 'confirmed_by_caregiver',
        ]]);

        $memory = ChildMemory::firstOrFail();
        $this->assertSame('The child uses Arabic and English.', $memory->content);
        $this->assertCount(1, data_get($memory->metadata, 'history', []));
    }
}
