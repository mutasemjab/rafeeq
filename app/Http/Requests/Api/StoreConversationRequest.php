<?php

namespace App\Http\Requests\Api;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'child_id' => 'nullable|exists:children,id|prohibits:person_profile_id',
            'person_profile_id' => 'nullable|integer|exists:person_profiles,id|prohibits:child_id',
            'is_temporary' => 'sometimes|boolean',
            'temporary_subject' => 'sometimes|array:has_permission,age_months,relationship,communication_preferences,reported_diagnosis,diagnosis_source|prohibits:child_id,person_profile_id',
            'temporary_subject.has_permission' => $this->has('temporary_subject') ? 'required|accepted' : 'sometimes|accepted',
            'temporary_subject.age_months' => 'nullable|integer|min:0|max:1560',
            'temporary_subject.relationship' => 'required_with:temporary_subject|in:self,caregiver',
            'temporary_subject.communication_preferences' => 'nullable|string|max:1000',
            'temporary_subject.reported_diagnosis' => 'nullable|string|max:1000',
            'temporary_subject.diagnosis_source' => 'nullable|in:self_report,caregiver_report,professional_report',
            'title'    => 'nullable|string|max:255',
            'source'   => ['nullable', Rule::in(Conversation::acceptedInputSources())],
        ];
    }
}
