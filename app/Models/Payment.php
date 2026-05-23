<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'payable_type',
        'payable_id',
        'payment_type',
        'provider',
        'provider_payment_intent_id',
        'amount',
        'currency',
        'status',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'paid_at'  => 'datetime',
        'metadata' => 'array',
        'amount'   => 'float',
    ];

    public function payable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
