<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Sale
 */
class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'farm_id'          => $this->farm_id,
            'flock_id'         => $this->flock_id,
            'sale_date'        => $this->sale_date?->toDateString(),
            'reference_no'     => $this->reference_no,
            'buyer_name'       => $this->buyer_name,
            'gross_amount'     => $this->gross_amount,
            'discount_amount'  => $this->discount_amount,
            'net_amount'       => $this->net_amount,
            'received_amount'  => $this->received_amount,
            'remaining_amount' => $this->remaining_amount,
            'payment_status'   => $this->payment_status,
            'notes'            => $this->notes,
            'items'            => SaleItemResource::collection($this->whenLoaded('saleItems')),
            'created_at'       => $this->created_at?->toISOString(),
            'updated_at'       => $this->updated_at?->toISOString(),
        ];
    }
}
