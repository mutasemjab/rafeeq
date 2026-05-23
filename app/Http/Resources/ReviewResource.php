<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'appointment_id' => $this->appointment_id,
            'specialist_id'  => $this->specialist_id,
            'rating'         => $this->rating,
            'review'         => $this->review,
            'status'         => $this->status,
            'created_at'     => $this->created_at?->toISOString(),
        ];
    }
}
