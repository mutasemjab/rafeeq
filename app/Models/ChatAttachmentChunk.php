<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatAttachmentChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_attachment_id',
        'conversation_id',
        'user_id',
        'child_id',
        'chunk_index',
        'page_number',
        'section_title',
        'content',
        'token_count',
        'metadata',
        'embedding',
        'embedding_dimensions',
    ];

    protected $casts = [
        'metadata' => 'array',
        // embedding is NOT cast — stored as raw JSON string in LONGTEXT
    ];

    public function chatAttachment()
    {
        return $this->belongsTo(ChatAttachment::class);
    }

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
