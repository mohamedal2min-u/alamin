<?php

namespace App\Http\Resources;

use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Partner
 */
class PartnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $activeShare = $this->shares
            ?->firstWhere('is_active', true);

        return [
            'id'            => $this->id,
            'farm_id'       => $this->farm_id,
            'user_id'       => $this->user_id,
            'name'          => $this->name,
            'email'         => $this->email,
            'whatsapp'      => $this->whatsapp,
            'status'        => $this->status,
            'notes'         => $this->notes,
            'share_percent' => $activeShare ? (float) $activeShare->share_percent : 0.0,
            'shares'        => $this->whenLoaded('shares', fn () =>
                $this->shares->map(fn ($s) => [
                    'id'             => $s->id,
                    'share_percent'  => (float) $s->share_percent,
                    'is_active'      => $s->is_active,
                    'effective_from' => $s->effective_from,
                    'effective_to'   => $s->effective_to ?? null,
                ])->values()
            ),
            'user'          => $this->whenLoaded('user', fn () => [
                'id'       => $this->user->id,
                'name'     => $this->user->name,
                'email'    => $this->user->email,
                'whatsapp' => $this->user->whatsapp,
            ]),
            'created_at'    => $this->created_at->toISOString(),
            'updated_at'    => $this->updated_at->toISOString(),
        ];
    }
}
