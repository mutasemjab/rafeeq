<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatTurn extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['attempt' => 1];

    protected $casts = [
        'error' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'attempt' => 'integer',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function userMessage()
    {
        return $this->belongsTo(Message::class, 'user_message_id');
    }

    public function assistantMessage()
    {
        return $this->belongsTo(Message::class, 'assistant_message_id');
    }
}
