<?php

namespace Tests\Unit;

use App\Jobs\ProcessChildDocumentJob;
use App\Models\Child;
use App\Models\ChildDocument;
use App\Models\User;
use App\Services\Privacy\AiConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ChildDocumentConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_acceptance_starts_only_owned_pending_document_extraction_after_response(): void
    {
        Bus::fake();
        $user = User::factory()->create(['ai_consent_accepted_at' => null]);
        $child = Child::factory()->create(['user_id' => $user->id]);
        $pending = $this->document($child, 'uploaded');
        $this->document($child, 'processed');
        $this->document($child, 'failed');
        $this->document(Child::factory()->create(), 'uploaded');

        $snapshot = (new AiConsentService())->save($user, true);

        $this->assertTrue($snapshot['hasAiConsent']);
        Bus::assertDispatchedAfterResponse(ProcessChildDocumentJob::class, 1);
        Bus::assertDispatchedAfterResponse(ProcessChildDocumentJob::class, fn ($job) => $job->documentId === $pending->id);
    }

    public function test_withdrawal_and_repeated_acceptance_do_not_start_extraction(): void
    {
        Bus::fake();
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $this->document(Child::factory()->create(['user_id' => $user->id]), 'uploaded');
        $service = new AiConsentService();

        $service->save($user, true);
        $snapshot = $service->save($user, false);

        $this->assertFalse($snapshot['hasAiConsent']);
        Bus::assertNotDispatched(ProcessChildDocumentJob::class);
    }

    public function test_acceptance_uses_a_configured_durable_queue(): void
    {
        Bus::fake();
        config(['queue.default' => 'database']);
        $user = User::factory()->create(['ai_consent_accepted_at' => null]);
        $this->document(Child::factory()->create(['user_id' => $user->id]), 'uploaded');

        (new AiConsentService())->save($user, true);

        Bus::assertDispatched(ProcessChildDocumentJob::class, 1);
        Bus::assertNotDispatchedAfterResponse(ProcessChildDocumentJob::class);
    }

    public function test_acceptance_has_a_fixed_limit_on_scheduled_extractions(): void
    {
        Bus::fake();
        $user = User::factory()->create(['ai_consent_accepted_at' => null]);
        $child = Child::factory()->create(['user_id' => $user->id]);
        for ($i = 0; $i < 23; $i++) {
            $this->document($child, 'uploaded');
        }

        (new AiConsentService())->save($user, true);

        Bus::assertDispatchedAfterResponse(ProcessChildDocumentJob::class, 20);
    }

    private function document(Child $child, string $status): ChildDocument
    {
        return ChildDocument::create([
            'child_id' => $child->id,
            'user_id' => $child->user_id,
            'original_name' => 'report.txt',
            'file_path' => "children/{$child->id}/report.txt",
            'status' => $status,
        ]);
    }
}
