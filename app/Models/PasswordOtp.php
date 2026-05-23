<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasswordOtp extends Model
{
    use HasFactory;

    protected $table = 'password_otps';

    protected $fillable = [
        'user_id',
        'email',
        'phone',
        'code',
        'purpose',
        'expires_at',
        'consumed_at',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isConsumed();
    }
}
