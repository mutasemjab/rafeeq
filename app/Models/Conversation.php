<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    public const SOURCE_INPUT_ALIASES = [
        'text' => 'text',
        'voice' => 'voice',
        'app' => 'text',
        'mobile' => 'text',
        'web' => 'text',
    ];

    protected $fillable = [
        'user_id',
        'child_id',
        'person_profile_id',
        'is_temporary', 'expires_at', 'temporary_subject',
        'title',
        'summary',
        'active_domain',
        'case_state',
        'next_question',
        'last_planned_at',
        'source',
        'status',
        'message_count',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'last_planned_at' => 'datetime',
        'case_state' => 'array',
        'is_temporary' => 'boolean', 'expires_at' => 'datetime', 'temporary_subject' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('unexpired', fn ($query) => $query->where(function ($expiry) {
            $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now());
        }));
    }

    public static function acceptedInputSources(): array
    {
        return array_keys(self::SOURCE_INPUT_ALIASES);
    }

    public static function normalizeSource(?string $source): string
    {
        if ($source === null || trim($source) === '') {
            return 'text';
        }

        return self::SOURCE_INPUT_ALIASES[$source] ?? 'text';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function child()
    {
        return $this->belongsTo(Child::class);
    }

    public function personProfile()
    {
        return $this->belongsTo(PersonProfile::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function chatAttachments()
    {
        return $this->hasMany(ChatAttachment::class);
    }
}
