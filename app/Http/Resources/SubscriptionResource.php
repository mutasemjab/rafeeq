<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'plan'           => new PlanResource($this->whenLoaded('plan')),
            'status'         => $this->status,
            'starts_at'      => $this->starts_at?->toISOString(),
            'ends_at'        => $this->ends_at?->toISOString(),
            'trial_ends_at'  => $this->trial_ends_at?->toISOString(),
            'created_at'     => $this->created_at?->toISOString(),
        ];
    }
}
