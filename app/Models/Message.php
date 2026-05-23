<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'child_id',
        'role',
        'content',
        'input_type',
        'source_type',
        'sources',
        'metadata',
        'model_name',
        'token_usage_input',
        'token_usage_output',
        'safety_flags',
    ];

    protected $casts = [
        'sources'      => 'array',
        'metadata'     => 'array',
        'safety_flags' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function child()
    {
        return $this->belongsTo(Child::class);
    }
}
