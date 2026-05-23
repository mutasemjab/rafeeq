<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChildResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'date_of_birth'     => $this->date_of_birth?->toDateString(),
            'age'               => $this->date_of_birth ? $this->date_of_birth->age : null,
            'gender'            => $this->gender,
            'diagnosis'         => $this->diagnosis,
            'diagnosis_details' => $this->diagnosis_details,
            'notes'             => $this->notes,
            'avatar'            => $this->avatar ? asset('storage/' . $this->avatar) : null,
            'created_at'        => $this->created_at?->toISOString(),
            'updated_at'        => $this->updated_at?->toISOString(),
        ];
    }
}
