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
        'ai_messages_per_day',
        'max_children',
        'max_documents_per_child',
        'has_specialist_access',
        'has_voice_mode',
        'has_progress_reports',
        'has_priority_support',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'has_specialist_access'   => 'bool',
        'has_voice_mode'          => 'bool',
        'has_progress_reports'    => 'bool',
        'has_priority_support'    => 'bool',
        'is_active'               => 'bool',
        'metadata'                => 'array',
        'price'                   => 'float',
    ];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}
