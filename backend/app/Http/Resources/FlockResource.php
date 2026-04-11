<?php

namespace App\Http\Resources;

use App\Models\Flock;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Flock
 */
class FlockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // current_age_days: محسوب ديناميكياً للأفواج النشطة والمسودة
        $agedays = null;
        if (in_array($this->status, ['active', 'draft'])) {
            $agedays = (int) Carbon::parse($this->start_date)->diffInDays(Carbon::today());
        }

        return [
            'id'               => $this->id,
            'farm_id'          => $this->farm_id,
            'name'             => $this->name,
            'status'           => $this->status,
            'start_date'       => $this->start_date->toDateString(),
            'end_date'         => $this->close_date?->toDateString(), // frontend expects end_date
            'initial_count'    => $this->initial_count,
            'current_age_days' => $agedays,
            'notes'            => $this->notes,
            'created_at'       => $this->created_at->toISOString(),
            'updated_at'       => $this->updated_at->toISOString(),
        ];
    }
}
