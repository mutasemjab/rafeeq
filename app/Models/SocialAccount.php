<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialAccount extends Model
{
    use HasFactory;

    protected $table = 'social_accounts';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'provider_email',
        'provider_data',
    ];

    protected $casts = [
        'provider_data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
