<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonProfile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'legacy_child_id', 'display_name', 'birth_date', 'age_months',
        'relationship', 'preferred_language', 'communication_preferences',
        'reported_diagnosis', 'diagnosis_source', 'permission_attested_at',
        'ai_consent_accepted_at', 'persistence_consent_accepted_at', 'consent_version',
    ];

    protected $casts = [
        'birth_date' => 'date', 'age_months' => 'integer',
        'permission_attested_at' => 'datetime', 'ai_consent_accepted_at' => 'datetime',
        'persistence_consent_accepted_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }

    public function legacyChild() { return $this->belongsTo(Child::class, 'legacy_child_id'); }

    public function conversations() { return $this->hasMany(Conversation::class); }

    public function memories() { return $this->hasMany(PersonMemory::class); }

    public function effectiveAgeMonths(): ?int
    {
        return $this->birth_date !== null ? max(0, (int) $this->birth_date->diffInMonths(now())) : $this->age_months;
    }

    public function ageGroup(): ?string
    {
        $age = $this->effectiveAgeMonths();

        return $age === null ? null : ($age < 156 ? 'child' : ($age < 216 ? 'teen' : ($age < 780 ? 'adult' : 'older_adult')));
    }

    public function hasProcessingConsent(): bool
    {
        return $this->permission_attested_at !== null && $this->ai_consent_accepted_at !== null
            && $this->persistence_consent_accepted_at !== null;
    }
}
