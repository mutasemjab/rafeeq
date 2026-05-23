<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KnowledgeDocument extends Model
{
    use HasFactory, SoftDeletes;

    public const IN_PROGRESS_STATUSES = ['uploaded', 'processing'];

    protected $fillable = [
        'title',
        'original_name',
        'file_path',
        'mime_type',
        'file_size',
        'category',
        'status',
        'uploaded_by',
        'processing_error',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function chunks()
    {
        return $this->hasMany(KnowledgeDocumentChunk::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public static function titleFromFilename(string $filename): string
    {
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $title = preg_replace('/[_-]+/', ' ', $baseName);
        $title = preg_replace('/\s+/', ' ', $title);

        return trim($title) ?: $baseName;
    }
}
