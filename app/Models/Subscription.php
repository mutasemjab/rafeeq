<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'provider',
        'provider_customer_id',
        'provider_subscription_id',
        'status',
        'starts_at',
        'ends_at',
        'canceled_at',
        'renews_at',
        'metadata',
    ];

    protected $casts = [
        'starts_at'   => 'datetime',
        'ends_at'     => 'datetime',
        'canceled_at' => 'datetime',
        'renews_at'   => 'datetime',
        'metadata'    => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
