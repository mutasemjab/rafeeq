<?php

namespace App\Http\Resources;

use App\Services\Documents\PrivateDocumentStorage;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonDocumentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id, 'person_profile_id' => $this->person_profile_id,
            'title' => $this->title ?: $this->original_name, 'document_type' => $this->category,
            'file_url' => app(PrivateDocumentStorage::class)->temporaryUrl($this->resource),
            'status' => $this->status,
            'processing_error' => $this->status === 'failed' ? 'Could not read this file. Retry or upload a clearer supported document.' : null,
            'processed_at' => $this->processed_at?->toISOString(), 'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
