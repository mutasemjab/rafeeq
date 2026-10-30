<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KnowledgeDocumentChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'knowledge_document_id',
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

    protected static function booted(): void
    {
        static::saving(function (self $chunk): void {
            if ($chunk->isDirty(['embedding', 'embedding_dimensions'])) {
                $chunk->embedding_vector = \App\Services\Search\PackedEmbedding::encode($chunk->embedding, (int) $chunk->embedding_dimensions);
            }
        });
    }

    public function knowledgeDocument()
    {
        return $this->belongsTo(KnowledgeDocument::class);
    }
}
