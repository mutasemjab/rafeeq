<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonDocument extends Model
{
    use SoftDeletes;

    protected $fillable = ['person_profile_id', 'user_id', 'original_name', 'title', 'file_path',
        'mime_type', 'file_size', 'category', 'status', 'storage_disk', 'has_legacy_public_copy',
        'processing_error', 'processed_at', 'metadata'];

    protected $casts = ['metadata' => 'array', 'processed_at' => 'datetime', 'has_legacy_public_copy' => 'boolean'];

    public function personProfile() { return $this->belongsTo(PersonProfile::class); }

    public function user() { return $this->belongsTo(User::class); }

    public function canProcess(): bool
    {
        return $this->user()->whereNotNull('ai_consent_accepted_at')->exists()
            && $this->personProfile()->where('user_id', $this->user_id)
                ->whereNotNull('ai_consent_accepted_at')->whereNotNull('persistence_consent_accepted_at')
                ->whereNotNull('permission_attested_at')->exists();
    }
}
