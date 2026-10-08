<?php

namespace App\Services\AI;

use App\Models\Child;
use App\Models\ChildMemory;
use App\Models\Conversation;
use App\Models\PersonMemory;
use App\Models\PersonProfile;
use App\Models\ChatTurn;
use App\Models\Message;

class ProfileFactCorrectionService
{
    /** Explicit profile edits must not be undone by an older chat memory. */
    public function ageChanged(Child|PersonProfile $profile): void
    {
        $person = $profile instanceof PersonProfile;
        $model = $person ? PersonMemory::class : ChildMemory::class;
        $parentKey = $person ? 'person_profile_id' : 'child_id';
        $memories = $model::where($parentKey, $profile->id)->where('user_id', $profile->user_id)->where('status', 'active')->get();
        foreach ($memories as $memory) {
            if ($this->isAgeKey($memory->memory_key)) { $memory->update(['status' => 'superseded']); }
        }
        foreach (Conversation::withoutGlobalScope('unexpired')->withTrashed()->where($parentKey, $profile->id)->where('user_id', $profile->user_id)->get() as $conversation) {
            foreach (ChatTurn::where('conversation_id', $conversation->id)->whereIn('status', ['queued','processing'])->get() as $turn) {
                if ($reply = Message::where('reply_to_message_id', $turn->user_message_id)->where('conversation_id', $conversation->id)->first()) {
                    $reply->update(['reply_to_message_id'=>null, 'metadata'=>array_merge((array)$reply->metadata,[
                        'context_superseded'=>true,'superseded_client_message_id'=>data_get($reply->metadata,'client_message_id'),
                        'client_message_id'=>null,'user_message_id'=>$turn->user_message_id,
                    ])]);
                }
                $turn->update(['status'=>'failed','stage'=>'failed','attempt'=>$turn->attempt+1,'assistant_message_id'=>null,
                    'completed_at'=>now(),'error'=>['code'=>'CASE_CONTEXT_CHANGED','message'=>'Profile age changed. Retry to use the corrected information.','retryable'=>true]]);
                if ($userMessage = $turn->userMessage) {
                    $userMessage->update(['metadata'=>array_merge((array)$userMessage->metadata,['delivery_status'=>'failed'])]);
                }
            }
            $state = (array) $conversation->case_state;
            foreach (array_keys((array) ($state['fact_index'] ?? [])) as $key) {
                if ($this->isAgeKey($key)) { unset($state['fact_index'][$key]); }
            }
            $state['known_facts'] = [];
            $state['profile_age_updated_at'] = now()->toISOString();
            $state['pathway_state']['decisions_invalidated'] = true;
            foreach (array_keys((array) data_get($state, 'pathway_state.answers', [])) as $key) {
                if ($key === 'gateway:G02' || str_ends_with($key, ':P02')) { unset($state['pathway_state']['answers'][$key]); }
            }
            $state['pathway_state']['pending_question_id'] = null;
            $state['progress']['status'] = 'needs_review_after_profile_correction';
            $conversation->update(['case_state' => $state, 'summary' => null, 'next_question' => null]);
        }
        if (! $person) {
            foreach (PersonProfile::where('legacy_child_id', $profile->id)->where('user_id', $profile->user_id)->get() as $linked) {
                $linked->update(['birth_date'=>$profile->birth_date,'age_months'=>$profile->birth_date === null && $profile->age !== null ? (int)$profile->age*12 : null,'age_updated_at'=>now()]);
                $this->ageChanged($linked);
            }
        }
    }

    private function isAgeKey(string $key): bool
    {
        return in_array(ChildMemoryManager::canonicalKey($key), ['child.age', 'child.birth_date'], true);
    }
}
