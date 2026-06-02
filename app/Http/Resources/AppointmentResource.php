<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray($request): array
    {
        $payment = $this->relationLoaded('payment') ? $this->payment : null;

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
            'payment'           => $payment ? [
                'id' => $payment->id,
                'type' => $payment->payment_type,
                'provider' => $payment->provider,
                'method' => data_get($payment->metadata, 'payment_method'),
                'status' => $payment->status,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'test_only' => (bool) data_get($payment->metadata, 'test_only', false),
            ] : null,
            'created_at'        => $this->created_at?->toISOString(),
        ];
    }
}
