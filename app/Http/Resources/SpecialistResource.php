<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SpecialistResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'title'            => $this->title,
            'bio'              => $this->bio,
            'specializations'  => $this->specializations,
            'languages'        => $this->languages,
            'avatar'           => $this->avatar ? asset('storage/' . $this->avatar) : null,
            'session_fee'      => $this->session_fee,
            'currency'         => $this->currency,
            'rating_avg'       => $this->rating_avg,
            'reviews_count'    => $this->reviews_count,
            'is_available'     => $this->is_available,
            'created_at'       => $this->created_at?->toISOString(),
        ];
    }
}
