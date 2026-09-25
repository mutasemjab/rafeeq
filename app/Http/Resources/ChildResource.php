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
            'date_of_birth'     => $this->birth_date?->toDateString(),
            'age'               => $this->birth_date ? $this->birth_date->age : $this->age,
            'gender'            => $this->gender,
            'diagnosis'         => $this->diagnosis,
            'diagnosis_details' => $this->condition_notes,
            'notes'             => $this->general_notes,
            'communication_notes' => $this->communication_notes,
            'behavior_notes' => $this->behavior_notes,
            'school_notes' => $this->school_notes,
            'therapy_notes' => $this->therapy_notes,
            'medical_notes' => $this->medical_notes,
            'avatar' => $this->avatar ? asset('storage/'.$this->avatar) : null,
            'created_at'        => $this->created_at?->toISOString(),
            'updated_at'        => $this->updated_at?->toISOString(),
        ];
    }
}
