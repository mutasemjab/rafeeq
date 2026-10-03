<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChildDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'child_id',
        'user_id',
        'original_name',
        'title',
        'file_path',
        'mime_type',
        'file_size',
        'category',
        'status',
        'processing_error',
        'processed_at',
        'metadata',
        'storage_disk',
        'has_legacy_public_copy',
    ];

    protected $casts = [
        'metadata' => 'array',
        'processed_at' => 'datetime',
        'has_legacy_public_copy' => 'boolean',
    ];

    public function child()
    {
        return $this->belongsTo(Child::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
