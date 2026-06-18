<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'device_id',
        'is_active',
        'first_name',
        'last_name',
        'role',
        'avatar',
        'preferred_language',
        'theme_preference',
        'status',
        'phone_verified_at',
        'last_login_at',
        'ai_consent_accepted_at',
        'ai_consent_version',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at'  => 'datetime',
        'phone_verified_at'  => 'datetime',
        'last_login_at'      => 'datetime',
        'ai_consent_accepted_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function children()
    {
        return $this->hasMany(Child::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    public function chatAttachments()
    {
        return $this->hasMany(ChatAttachment::class);
    }

    public function childDocuments()
    {
        return $this->hasMany(ChildDocument::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function devices()
    {
        return $this->hasMany(UserDevice::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Return the user's currently active subscription (with plan loaded),
     * or null if none exists.
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->with('plan')
            ->where('status', 'active')
            ->latest()
            ->first();
    }

    public function hasAiConsent(): bool
    {
        return $this->ai_consent_accepted_at !== null;
    }

    public function aiConsentSnapshot(): array
    {
        return [
            'hasAiConsent' => $this->hasAiConsent(),
            'acceptedAt' => $this->ai_consent_accepted_at?->toISOString(),
            'version' => $this->ai_consent_version,
        ];
    }

    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = is_string($value)
            ? mb_strtolower(trim($value))
            : $value;
    }

    // -------------------------------------------------------------------------
    // Legacy
    // -------------------------------------------------------------------------

    public function get_code()
    {
        return $this->hasOne('App\Models\Code', 'customer_id', 'id');
    }
}
