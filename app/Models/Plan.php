<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'billing_period',
        'price',
        'currency',
        'ai_daily_limit',
        'child_limit',
        'has_voice_mode',
        'has_advanced_ai',
        'has_evaluation_reports',
        'has_priority_support',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'has_voice_mode'         => 'bool',
        'has_advanced_ai'        => 'bool',
        'has_evaluation_reports' => 'bool',
        'has_priority_support'   => 'bool',
        'is_active'              => 'bool',
        'metadata'               => 'array',
        'price'                  => 'float',
    ];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}
