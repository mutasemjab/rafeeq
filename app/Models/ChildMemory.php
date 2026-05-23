<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChildMemory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'child_id',
        'user_id',
        'type',
        'title',
        'content',
        'confidence',
        'source',
        'source_message_id',
        'metadata',
    ];

    protected $casts = [
        'confidence' => 'float',
        'metadata'   => 'array',
    ];

    public function child()
    {
        return $this->belongsTo(Child::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sourceMessage()
    {
        return $this->belongsTo(Message::class, 'source_message_id');
    }
}
