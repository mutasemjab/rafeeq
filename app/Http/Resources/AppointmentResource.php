<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'booking_reference' => $this->booking_reference,
            'specialist'        => new SpecialistResource($this->whenLoaded('specialist')),
            'child_id'          => $this->child_id,
            'appointment_type'  => $this->appointment_type,
            'scheduled_date'    => $this->scheduled_date?->toDateString(),
            'start_time'        => $this->start_time,
            'end_time'          => $this->end_time,
            'timezone'          => $this->timezone,
            'status'            => $this->status,
            'join_url'          => $this->join_url,
            'join_available_at' => $this->join_available_at?->toISOString(),
            'notes'             => $this->notes,
            'created_at'        => $this->created_at?->toISOString(),
        ];
    }
}
