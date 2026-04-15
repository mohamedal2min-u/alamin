<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\FlockWaterLog
 */
class FlockWaterLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'flock_id'   => $this->flock_id,
            'entry_date' => $this->entry_date?->toDateString(),
            'quantity'   => $this->quantity !== null ? (float) $this->quantity : null,
            'unit_label' => $this->unit_label,
            'notes'      => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
