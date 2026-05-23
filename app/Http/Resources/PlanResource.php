<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                     => $this->id,
            'name'                   => $this->name,
            'slug'                   => $this->slug,
            'type'                   => $this->type,
            'billing_period'         => $this->billing_period,
            'price'                  => $this->price,
            'currency'               => $this->currency,
            'ai_messages_per_day'    => $this->ai_messages_per_day,
            'max_children'           => $this->max_children,
            'max_documents_per_child'=> $this->max_documents_per_child,
            'has_specialist_access'  => $this->has_specialist_access,
            'has_voice_mode'         => $this->has_voice_mode,
            'has_progress_reports'   => $this->has_progress_reports,
            'is_active'              => $this->is_active,
        ];
    }
}
