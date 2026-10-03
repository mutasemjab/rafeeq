<?php

namespace Tests\Unit;

use App\Models\Child;
use App\Models\Conversation;
use App\Models\User;
use App\Services\AI\CaseBriefService;
use App\Services\AI\ChildContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseBriefServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_and_longitudinal_context_is_bounded_and_keeps_provenance(): void
    {
        $brief = CaseBriefService::build(['summary' => str_repeat('ن', 8000)], [
            'known_facts' => ['Reported age five'],
            'progress' => ['recommended_step' => 'Try a predictable cue.', 'status' => 'awaiting_observation'],
        ], 'Previous session summary.');
        $this->assertSame(6500, mb_strlen($brief['longitudinal_summary']));
        $this->assertSame('Previous session summary.', $brief['conversation_summary']);
        $this->assertStringContainsString('not a caregiver fact', $brief['progress']['recommendation_provenance']);
    }

    public function test_a_new_conversation_receives_only_owned_progress_for_the_selected_child(): void
    {
        $user = User::factory()->create();
        $child = Child::factory()->create(['user_id' => $user->id]);
        Conversation::factory()->create(['user_id' => $user->id, 'child_id' => $child->id,
            'summary' => 'Used a visual cue.', 'case_state' => ['progress' => ['recommended_step' => 'Visual cue', 'waiting_for' => 'observation']]]);
        Conversation::factory()->create(['user_id' => User::factory(), 'child_id' => $child->id,
            'summary' => 'Foreign private summary.', 'case_state' => ['progress' => ['recommended_step' => 'Foreign private step']]]);
        $current = Conversation::factory()->create(['user_id' => $user->id, 'child_id' => $child->id]);
        $context = (new ChildContextService())->build($child->id, $user->id, $current);
        $this->assertCount(1, $context['case_brief']['previous_progress']);
        $this->assertSame('Visual cue', $context['case_brief']['previous_progress'][0]['recommended_step']);
        $this->assertStringNotContainsString('Foreign', json_encode($context));
    }
}
