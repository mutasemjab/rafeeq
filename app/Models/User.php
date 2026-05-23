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

    // -------------------------------------------------------------------------
    // Legacy
    // -------------------------------------------------------------------------

    public function get_code()
    {
        return $this->hasOne('App\Models\Code', 'customer_id', 'id');
    }
}
