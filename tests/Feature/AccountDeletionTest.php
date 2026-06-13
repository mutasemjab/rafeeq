<?php

namespace Tests\Feature;

use App\Models\ChatAttachment;
use App\Models\Child;
use App\Models\ChildDocument;
use App\Models\Conversation;
use App\Models\PasswordOtp;
use App\Models\RafiqNotification;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        Storage::fake('public');
    }

    public function test_authenticated_user_can_delete_their_own_account(): void
    {
        $user = User::factory()->create([
            'email' => 'parent@example.com',
            'phone' => '123456',
            'avatar' => 'avatars/user-avatar.png',
        ]);

        Storage::disk('public')->put('avatars/user-avatar.png', 'avatar');
        $child = Child::query()->create([
            'user_id' => $user->id,
            'name' => 'Omar',
            'birth_date' => '2020-01-01',
            'gender' => 'male',
        ]);

        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'child_id' => $child->id,
        ]);

        $documentPath = "children/{$child->id}/documents/report.pdf";
        $attachmentPath = "chat-attachments/{$user->id}/{$conversation->id}/chat.pdf";

        Storage::disk('public')->put($documentPath, 'doc');
        Storage::disk('public')->put($attachmentPath, 'chat');

        $document = ChildDocument::query()->create([
            'child_id' => $child->id,
            'user_id' => $user->id,
            'title' => 'Assessment',
            'original_name' => 'report.pdf',
            'file_path' => $documentPath,
            'mime_type' => 'application/pdf',
            'file_size' => 1000,
            'status' => 'uploaded',
        ]);

        $attachment = ChatAttachment::query()->create([
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'child_id' => $child->id,
            'original_name' => 'chat.pdf',
            'file_path' => $attachmentPath,
            'mime_type' => 'application/pdf',
            'file_size' => 1000,
            'status' => 'uploaded',
        ]);

        RafiqNotification::factory()->create([
            'user_id' => $user->id,
        ]);

        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'apple',
            'provider_user_id' => 'apple-user-123',
            'provider_email' => $user->email,
            'provider_data' => ['sub' => 'apple-user-123'],
        ]);

        UserDevice::query()->create([
            'user_id' => $user->id,
            'platform' => 'ios',
            'push_token' => 'push-token',
        ]);

        PasswordOtp::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'phone' => $user->phone,
            'code' => '123456',
            'purpose' => 'reset_password',
            'expires_at' => now()->addMinutes(10),
        ]);

        $token = $user->createToken('mobile')->accessToken;

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson('/api/v1/user/account')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Account deleted successfully');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('children', ['id' => $child->id]);
        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('child_documents', ['id' => $document->id]);
        $this->assertDatabaseMissing('chat_attachments', ['id' => $attachment->id]);
        $this->assertDatabaseMissing('rafiq_notifications', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('social_accounts', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('user_devices', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('password_otps', ['email' => $user->email]);

        if (Schema::hasTable('oauth_access_tokens')) {
            $this->assertSame(0, DB::table('oauth_access_tokens')->where('user_id', $user->id)->count());
        }

        Storage::disk('public')->assertMissing('avatars/user-avatar.png');
        Storage::disk('public')->assertMissing($documentPath);
        Storage::disk('public')->assertMissing($attachmentPath);
    }

    public function test_deleted_user_tokens_become_invalid(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->accessToken;

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson('/api/v1/user/account')
            ->assertOk();

        app('auth')->forgetGuards();

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_unauthenticated_request_cannot_delete_an_account(): void
    {
        $this->deleteJson('/api/v1/user/account')
            ->assertStatus(401);
    }
}
