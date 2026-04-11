<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\FlockMortality
 */
class FlockMortalityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'flock_id'   => $this->flock_id,
            'entry_date' => $this->entry_date?->toDateString(),
            'quantity'   => $this->quantity,
            'reason'     => $this->reason,
            'notes'      => $this->notes,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
