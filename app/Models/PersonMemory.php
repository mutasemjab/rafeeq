<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonMemory extends Model
{
    protected $fillable = [
        'person_profile_id', 'user_id', 'source_message_id', 'memory_key', 'type',
        'title', 'content', 'status', 'confidence', 'source', 'last_confirmed_at', 'metadata',
    ];

    protected $casts = ['metadata' => 'array', 'confidence' => 'float', 'last_confirmed_at' => 'datetime'];

    public function personProfile() { return $this->belongsTo(PersonProfile::class); }

    public function sourceMessage() { return $this->belongsTo(Message::class, 'source_message_id'); }
}
