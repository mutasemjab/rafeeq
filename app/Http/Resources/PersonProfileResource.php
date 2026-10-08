<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PersonProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id, 'display_name' => $this->display_name,
            'legacy_child_id' => $this->legacy_child_id,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'age_months' => $this->effectiveAgeMonths(), 'age_group' => $this->ageGroup(),
            'relationship' => $this->relationship, 'preferred_language' => $this->preferred_language,
            'communication_preferences' => $this->communication_preferences,
            'reported_diagnosis' => $this->reported_diagnosis, 'diagnosis_source' => $this->diagnosis_source,
            'consent' => [
                'has_ai_consent' => $this->ai_consent_accepted_at !== null,
                'has_persistence_consent' => $this->persistence_consent_accepted_at !== null,
                'permission_attested_at' => $this->permission_attested_at?->toISOString(),
                'ai_accepted_at' => $this->ai_consent_accepted_at?->toISOString(),
                'persistence_accepted_at' => $this->persistence_consent_accepted_at?->toISOString(),
                'version' => $this->consent_version,
            ],
        ];
    }
}
