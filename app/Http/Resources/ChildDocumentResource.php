<?php

namespace App\Http\Resources;

use App\Services\Documents\PrivateDocumentStorage;
use Illuminate\Http\Resources\Json\JsonResource;

class ChildDocumentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'child_id' => $this->child_id,
            'title' => $this->title,
            'document_type' => $this->category,
            'file_path' => app(PrivateDocumentStorage::class)->temporaryUrl($this->resource),
            'file_url' => app(PrivateDocumentStorage::class)->temporaryUrl($this->resource),
            'download_url' => route('child-documents.download', ['child' => $this->child_id, 'document' => $this->id]),
            'status' => $this->status,
            'processing_error' => $this->status === 'failed' ? 'Could not read this file. Upload a clearer supported document.' : null,
            'processed_at' => $this->processed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
