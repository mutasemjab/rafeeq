<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KnowledgeDocument extends Model
{
    use HasFactory, SoftDeletes;

    public const IN_PROGRESS_STATUSES = ['uploaded', 'processing'];
    public const SUPPORTED_UPLOAD_EXTENSIONS = ['pdf', 'docx', 'doc', 'pptx', 'txt'];

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

    public static function supportedUploadExtensions(): array
    {
        return self::SUPPORTED_UPLOAD_EXTENSIONS;
    }

    public static function supportedUploadAcceptAttribute(): string
    {
        return collect(self::supportedUploadExtensions())
            ->map(fn(string $extension) => '.' . $extension)
            ->implode(',');
    }

    public static function uploadRules(int $maxKilobytes = 51200): array
    {
        return [
            'required',
            'file',
            'max:' . $maxKilobytes,
            function (string $attribute, mixed $file, \Closure $fail): void {
                $extension = strtolower((string) pathinfo(
                    $file?->getClientOriginalName() ?? '',
                    PATHINFO_EXTENSION
                ));

                if ($extension === 'ppt') {
                    $fail('Legacy PowerPoint .ppt files are not supported. Please save the file as .pptx and upload it again.');
                    return;
                }

                if (!in_array($extension, self::supportedUploadExtensions(), true)) {
                    $fail('The file must be a file of type: ' . implode(', ', self::supportedUploadExtensions()) . '.');
                }
            },
        ];
    }

    public static function titleFromFilename(string $filename): string
    {
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $title = preg_replace('/[_-]+/', ' ', $baseName);
        $title = preg_replace('/\s+/', ' ', $title);

        return trim($title) ?: $baseName;
    }
}
