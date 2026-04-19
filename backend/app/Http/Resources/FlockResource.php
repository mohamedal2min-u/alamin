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
            $agedays = (int) Carbon::parse($this->start_date)->startOfDay()
                ->diffInDays(Carbon::today()->startOfDay()) + 1;
        }

        $totalMortality = (int) ($this->mortalities_sum_quantity ?? 0);
        $totalBirdsSold = (int) ($this->sale_items_sum_birds_count ?? 0);

        $totalSales    = (float) ($this->sales_sum_net_amount ?? 0);
        $opExpenses    = (float) ($this->expenses_sum_total_amount ?? 0);
        $chickCost     = (float) ($this->total_chick_cost ?? 0);
        $inventoryCost = (float) ($this->inventory_consumption_sum ?? 0);
        $waterCost     = (float) ($this->water_logs_sum_total_amount ?? 0);

        // $opExpenses already includes "شراء كتاكيت" expense — do NOT add $chickCost to avoid double-counting
        $totalExpenses = $opExpenses + $inventoryCost + $waterCost;

        return [
            'id'               => $this->id,
            'farm_id'          => $this->farm_id,
            'name'             => $this->name,
            'status'           => $this->status,
            'start_date'       => $this->start_date->toDateString(),
            'end_date'         => $this->close_date?->toDateString(),
            'initial_count'    => $this->initial_count,
            'chick_unit_price' => (float) $this->chick_unit_price,
            'total_chick_cost' => $chickCost,
            'current_age_days' => $agedays,
            'total_mortality'  => $totalMortality,
            'remaining_count'  => $this->initial_count - $totalMortality - $totalBirdsSold,
            'total_sales'      => $totalSales,
            'total_expenses'   => $totalExpenses,
            'net_profit'       => $totalSales - $totalExpenses,
            'notes'            => $this->notes,
            'created_at'       => $this->created_at->toISOString(),
            'updated_at'       => $this->updated_at->toISOString(),
        ];
    }
}
