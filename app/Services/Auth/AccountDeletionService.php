<?php

namespace App\Services\Auth;

use App\Models\ChatAttachment;
use App\Models\ChildDocument;
use App\Models\PasswordOtp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AccountDeletionService
{
    public function delete(User $user): void
    {
        $filePaths = $this->collectFilePaths($user);

        DB::transaction(function () use ($user): void {
            $this->deleteAuthArtifacts($user);
            $this->deleteAncillaryArtifacts($user);
            $user->delete();
        });

        $this->deleteFiles($filePaths);

        Log::info('User account deleted', [
            'user_id' => $user->id,
        ]);
    }

    private function collectFilePaths(User $user): array
    {
        $paths = [];

        if (! empty($user->avatar)) {
            $paths[] = $user->avatar;
        }

        $paths = array_merge(
            $paths,
            ChildDocument::withTrashed()
                ->where('user_id', $user->id)
                ->pluck('file_path')
                ->filter()
                ->all(),
            ChatAttachment::withTrashed()
                ->where('user_id', $user->id)
                ->pluck('file_path')
                ->filter()
                ->all(),
        );

        return array_values(array_unique(array_filter($paths)));
    }

    private function deleteAuthArtifacts(User $user): void
    {
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        if (Schema::hasTable('oauth_access_tokens')) {
            $accessTokenIds = DB::table('oauth_access_tokens')
                ->where('user_id', $user->id)
                ->pluck('id')
                ->all();

            if ($accessTokenIds !== [] && Schema::hasTable('oauth_refresh_tokens')) {
                DB::table('oauth_refresh_tokens')
                    ->whereIn('access_token_id', $accessTokenIds)
                    ->delete();
            }

            DB::table('oauth_access_tokens')
                ->where('user_id', $user->id)
                ->delete();
        }

        if (Schema::hasTable('oauth_auth_codes')) {
            DB::table('oauth_auth_codes')
                ->where('user_id', $user->id)
                ->delete();
        }

        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $user->id)
                ->delete();
        }

        if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->delete();
        }
    }

    private function deleteAncillaryArtifacts(User $user): void
    {
        PasswordOtp::query()
            ->where('user_id', $user->id)
            ->when($user->email, fn ($query) => $query->orWhere('email', $user->email))
            ->when($user->phone, fn ($query) => $query->orWhere('phone', $user->phone))
            ->delete();

        if (Schema::hasTable('model_has_roles')) {
            DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->where('model_id', $user->id)
                ->delete();
        }

        if (Schema::hasTable('model_has_permissions')) {
            DB::table('model_has_permissions')
                ->where('model_type', User::class)
                ->where('model_id', $user->id)
                ->delete();
        }
    }

    private function deleteFiles(array $filePaths): void
    {
        if ($filePaths === []) {
            return;
        }

        Storage::disk('public')->delete($filePaths);
    }
}
