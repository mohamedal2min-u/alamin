<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SaleItem
 */
class SaleItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'sale_id'           => $this->sale_id,
            'flock_id'          => $this->flock_id,
            'birds_count'       => $this->birds_count,
            'total_weight_kg'   => $this->total_weight_kg,
            'avg_weight_kg'     => $this->avg_weight_kg,
            'unit_price_per_kg' => $this->unit_price_per_kg,
            'line_total'        => $this->line_total,
            'notes'             => $this->notes,
            'created_at'        => $this->created_at?->toISOString(),
            'updated_at'        => $this->updated_at?->toISOString(),
        ];
    }
}
