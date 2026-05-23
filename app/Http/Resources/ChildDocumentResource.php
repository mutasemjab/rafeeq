<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChildDocumentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'child_id'      => $this->child_id,
            'title'         => $this->title,
            'document_type' => $this->document_type,
            'file_path'     => $this->file_path ? asset('storage/' . $this->file_path) : null,
            'status'        => $this->status,
            'created_at'    => $this->created_at?->toISOString(),
        ];
    }
}
