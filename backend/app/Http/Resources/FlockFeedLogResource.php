<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\FlockFeedLog
 */
class FlockFeedLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'flock_id'          => $this->flock_id,
            'item_id'           => $this->item_id,
            'item_name'         => $this->item?->name,
            'item_input_unit'   => $this->item?->input_unit,
            'entry_date'        => $this->entry_date?->toDateString(),
            'quantity'          => (float) $this->quantity,
            'unit_label'        => $this->unit_label,
            'notes'             => $this->notes,
            'inventory_linked'  => $this->inventory_transaction_id !== null,
            'created_at'        => $this->created_at?->toISOString(),
        ];
    }
}
