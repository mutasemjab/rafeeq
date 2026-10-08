<?php

namespace Tests\Feature;

use App\Jobs\ProcessPersonDocumentJob;
use App\Models\ChatAttachment;
use App\Models\ChatTurn;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\PersonProfile;
use App\Models\User;
use App\Services\AI\ChatUsageService;
use App\Services\AI\ChildMemoryManager;
use App\Services\AI\PersonContextService;
use App\Services\Documents\DocumentTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AllAgesLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        $this->user=User::factory()->create(['ai_consent_accepted_at'=>now()]);
        $this->actingAs($this->user,'user-api');
        Storage::fake('private');
        Bus::fake();
    }

    public function test_temporary_subject_requires_permission_without_creating_a_persistent_person(): void
    {
        $data=['is_temporary'=>true,'temporary_subject'=>['relationship'=>'self','age_months'=>420,'has_permission'=>true]];
        $id=$this->postJson('/api/v1/conversations',$data)->assertCreated()->assertJsonPath('is_temporary',true)->json('id');
        $this->assertDatabaseCount('person_profiles',0);
        $this->getJson('/api/v1/conversations')->assertOk()->assertJsonCount(0,'data');
        $this->getJson('/api/v1/conversations/'.$id)->assertOk()->assertJsonPath('conversation.is_temporary',true);
        $data['temporary_subject']['has_permission']=false;
        $this->postJson('/api/v1/conversations',$data)->assertUnprocessable();
        $data['temporary_subject']['has_permission']=true;
        $data['is_temporary']=false;
        $this->postJson('/api/v1/conversations',$data)->assertUnprocessable();
    }

    public function test_expiry_denies_access_then_removes_content_files_and_preserves_daily_usage(): void
    {
        $conversation=Conversation::factory()->create(['user_id'=>$this->user->id,'is_temporary'=>true,'expires_at'=>now()->addMinute()]);
        Message::create(['user_id'=>$this->user->id,'conversation_id'=>$conversation->id,'role'=>'user','content'=>'Synthetic temporary text']);
        Storage::disk('private')->put('temporary/report.txt','Synthetic report');
        ChatAttachment::factory()->create(['user_id'=>$this->user->id,'conversation_id'=>$conversation->id,'file_path'=>'temporary/report.txt','storage_disk'=>'private']);
        $this->assertSame(1,app(ChatUsageService::class)->forUser($this->user)['used']);
        $conversation->update(['expires_at'=>now()->subMinute()]);
        $this->assertFalse(app(PersonContextService::class)->canProcess($conversation,$this->user->id));
        $this->getJson('/api/v1/conversations/'.$conversation->id)->assertNotFound();
        $this->artisan('conversations:purge-expired')->assertExitCode(0);
        $this->assertDatabaseCount('messages',0);
        Storage::disk('private')->assertMissing('temporary/report.txt');
        $this->assertSame(1,app(ChatUsageService::class)->forUser($this->user)['used']);
        $this->artisan('conversations:purge-expired')->assertExitCode(0);
        $this->assertSame(1,app(ChatUsageService::class)->forUser($this->user)['used']);
    }

    public function test_person_document_is_private_and_processing_rechecks_consent(): void
    {
        $person=$this->person();
        $file=UploadedFile::fake()->createWithContent('report.txt','Synthetic clinician report: communication preference is writing.');
        $doc=$this->post('/api/v1/person-profiles/'.$person->id.'/documents',['file'=>$file])->assertCreated()->json();
        $this->assertArrayNotHasKey('metadata',$doc);
        $this->assertArrayNotHasKey('file_path',$doc);
        Bus::assertDispatchedAfterResponse(ProcessPersonDocumentJob::class);
        $person->update(['ai_consent_accepted_at'=>null]);
        $extractor=\Mockery::mock(DocumentTextExtractor::class);
        $extractor->shouldReceive('extractFromAbsolutePath')->never();
        (new ProcessPersonDocumentJob($doc['id']))->handle($extractor);
        $this->actingAs(User::factory()->create(),'user-api')->getJson('/api/v1/person-profiles/'.$person->id.'/documents')->assertForbidden();
        $this->actingAs($this->user,'user-api')->deleteJson('/api/v1/person-profiles/'.$person->id.'/documents/'.$doc['id'])->assertOk();
        $this->get($doc['file_url'])->assertNotFound();
    }

    public function test_person_report_extraction_uses_no_shared_cache_and_is_visible_only_in_its_case(): void
    {
        $person=$this->person();
        Storage::disk('private')->put('people/report.txt','Synthetic report');
        $doc=$person->documents()->create(['user_id'=>$this->user->id,'original_name'=>'report.txt','file_path'=>'people/report.txt','file_size'=>16,'mime_type'=>'text/plain','storage_disk'=>'private']);
        $extractor=\Mockery::mock(DocumentTextExtractor::class);
        $extractor->shouldReceive('extractFromAbsolutePath')->once()->withArgs(fn($path,$mime,$cache)=>$cache===false)->andReturn([['text'=>'Synthetic report states a preference for writing.']]);
        (new ProcessPersonDocumentJob($doc->id))->handle($extractor);
        $this->assertSame('processed',$doc->fresh()->status);
        $conversation=Conversation::factory()->create(['user_id'=>$this->user->id,'person_profile_id'=>$person->id]);
        $context=app(\App\Services\AI\CaseDocumentContextService::class)->build($this->user->id,$conversation);
        $this->assertTrue($context['person_documents'][0]['content_available']);
        $other=$this->person();
        $otherConversation=Conversation::factory()->create(['user_id'=>$this->user->id,'person_profile_id'=>$other->id]);
        $otherContext=app(\App\Services\AI\CaseDocumentContextService::class)->build($this->user->id,$otherConversation);
        $this->assertSame([],$otherContext['person_documents']);
    }

    public function test_profile_age_edit_supersedes_old_memory_and_invalidates_queued_turn(): void
    {
        $person=$this->person();
        $conversation=Conversation::factory()->create(['user_id'=>$this->user->id,'person_profile_id'=>$person->id]);
        $source=Message::create(['user_id'=>$this->user->id,'conversation_id'=>$conversation->id,'role'=>'user','content'=>'I am 30 years old']);
        app(ChildMemoryManager::class)->applyPersonCandidates($person->id,$this->user->id,$source->id,[['key'=>'person.age','type'=>'general','content'=>'30 years','evidence'=>'30 years','confidence'=>1,'fact_status'=>'confirmed_by_caregiver']]);
        $this->postJson('/api/v1/conversations/'.$conversation->id.'/chat',['message'=>'Help me use the app','async'=>true,'client_message_id'=>'profile-change-test'])->assertStatus(202);
        $this->patchJson('/api/v1/person-profiles/'.$person->id,['age_months'=>480])->assertOk();
        $this->assertSame(0,$person->memories()->where('status','active')->count());
        $this->assertSame('CASE_CONTEXT_CHANGED',ChatTurn::sole()->error['code']);
        $this->assertSame('failed',ChatTurn::sole()->status);
        $this->assertSame(480,app(PersonContextService::class)->build($conversation->fresh(),$this->user->id)['profile']['age_months']);
    }

    private function person(): PersonProfile
    {
        return $this->user->personProfiles()->create(['relationship'=>'self','age_months'=>360,'permission_attested_at'=>now(),
            'ai_consent_accepted_at'=>now(),'persistence_consent_accepted_at'=>now(),'consent_version'=>'1.0']);
    }
}
