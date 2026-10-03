<?php

namespace App\Http\Resources;

use App\Services\Documents\PrivateDocumentStorage;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatAttachmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'status' => $this->status,
            'file_url' => app(PrivateDocumentStorage::class)->temporaryUrl($this->resource),
            'download_url' => route('attachments.download', ['attachment' => $this->id]),
            'processing_error' => $this->status === 'failed' ? 'Could not read this file. Retry processing or upload a clearer supported file.' : null,
            'can_retry' => $this->status === 'failed',
            'processed_at' => $this->processed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
