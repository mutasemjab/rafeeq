<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChatAttachmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'conversation_id' => $this->conversation_id,
            'original_name'   => $this->original_name,
            'mime_type'       => $this->mime_type,
            'file_size'       => $this->file_size,
            'status'          => $this->status,
            'file_url'        => $this->file_path ? asset('storage/' . $this->file_path) : null,
            'created_at'      => $this->created_at?->toISOString(),
        ];
    }
}
