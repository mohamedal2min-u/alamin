<?php

namespace App\Services;

use App\Actions\Flock\ResolveFlockZeroDateAction;
use App\Models\Flock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * مؤشرات أداء الفوج (FCR، النفوق، الوزن، EPEF، تكلفة الكيلو، سعر التعادل...).
 * كل مؤشر ينقصه مدخل يُعاد null (لا صفر ولا قسمة على صفر)، مع قائمة `missing` تشرح السبب.
 * التكاليف من FlockProfitService حتى تطابق صفحة الربح.
 */
class FlockKpiService
{
    /** معامل التحويل إلى كغ حسب وحدة المحتوى للصنف */
    private const KG_FACTORS = [
        'كغ' => 1, 'كجم' => 1, 'كغم' => 1, 'كلغ' => 1, 'كيلو' => 1, 'كيلوغرام' => 1, 'كيلوجرام' => 1, 'kg' => 1, 'kilo' => 1,
        'غرام' => 0.001, 'جرام' => 0.001, 'غ' => 0.001, 'جم' => 0.001, 'g' => 0.001, 'gram' => 0.001,
        'طن' => 1000, 'ton' => 1000, 't' => 1000,
    ];

    private const MESSAGES = [
        'initial_count' => 'لم يُسجَّل عدد الكتاكيت الابتدائي',
        'sales' => 'لا توجد مبيعات بعد، فالوزن الحي غير معروف',
        'sale_prices' => 'بعض المبيعات بلا سعر بعد (بانتظار المحاسب)',
        'feed' => 'لم يُسجَّل استهلاك علف لهذا الفوج',
        'feed_unit' => 'بعض أصناف العلف بوحدة غير الوزن فلم تُحتسب في الكيلوغرامات',
        'feed_prices' => 'بعض استهلاك العلف بلا تكلفة بعد',
        'costs' => 'لا توجد تكاليف مسجّلة بعد',
        'current_weight' => 'الوزن الحالي للطيور غير معروف (لا مبيعات بعد)',
    ];

    public function __construct(private FlockProfitService $profit) {}

    public function forFlock(Flock $flock): array
    {
        $missing = [];
        $initial = (int) $flock->initial_count;
        if ($initial <= 0) {
            $missing[] = 'initial_count';
        }

        // ── الطيور ─────────────────────────────────────────────────────────
        $mortality = (int) $flock->mortalities()->sum('quantity');

        $saleRows = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sale_items.flock_id', $flock->id)
            ->groupBy('sales.id', 'sales.sale_date', 'sales.net_amount')
            ->selectRaw('sales.id, sales.sale_date, sales.net_amount, SUM(sale_items.birds_count) as birds, SUM(sale_items.total_weight_kg) as kg')
            ->get();

        $birdsSold = (int) $saleRows->sum('birds');
        $soldKg = (float) $saleRows->sum('kg');
        $remaining = max($initial - $mortality - $birdsSold, 0);

        $mortalityPct = $initial > 0 ? $mortality / $initial * 100 : null;
        $livabilityPct = $initial > 0 ? ($initial - $mortality) / $initial * 100 : null;

        $avgWeight = ($birdsSold > 0 && $soldKg > 0) ? $soldKg / $birdsSold : null;
        if ($avgWeight === null) {
            $missing[] = 'sales';
        }

        // الوزن الحالي التقديري = متوسط وزن آخر يوم بيع (أقرب تقدير لوزن الطيور الباقية)
        $lastDate = $saleRows->max('sale_date');
        $last = $saleRows->where('sale_date', $lastDate);
        $lastBirds = (int) $last->sum('birds');
        $currentWeight = ($lastBirds > 0 && (float) $last->sum('kg') > 0) ? (float) $last->sum('kg') / $lastBirds : null;

        // كيلوغرامات الطيور الباقية في العنبر (تقدير) — صفر إن لم يبقَ شيء
        $remainingKg = $remaining === 0 ? 0.0 : ($currentWeight !== null ? $remaining * $currentWeight : null);
        if ($remaining > 0 && $currentWeight === null) {
            $missing[] = 'current_weight';
        }
        // الوزن الحي المُنتَج = المباع + الباقي التقديري
        $liveKg = ($soldKg > 0 && $remainingKg !== null) ? $soldKg + $remainingKg : null;

        $age = $this->ageDays($flock, $remaining);

        // ── العلف ──────────────────────────────────────────────────────────
        $feedRows = DB::table('inventory_transactions as t')
            ->join('items', 'items.id', '=', 't.item_id')
            ->join('item_types', 'item_types.id', '=', 'items.item_type_id')
            ->where('t.flock_id', $flock->id)
            ->where('t.direction', 'out')
            ->where('t.transaction_type', 'consumption')
            ->where('item_types.code', 'feed')
            ->selectRaw('COALESCE(t.content_unit, items.content_unit) as unit, SUM(t.computed_quantity) as qty, SUM(COALESCE(t.total_amount, 0)) as cost, SUM(CASE WHEN COALESCE(t.total_amount, 0) <= 0 THEN 1 ELSE 0 END) as unpriced')
            ->groupBy(DB::raw('COALESCE(t.content_unit, items.content_unit)'))
            ->get();

        $feedKg = null;
        $feedCost = (float) $feedRows->sum('cost');
        $unknownUnits = [];
        foreach ($feedRows as $row) {
            $factor = self::KG_FACTORS[mb_strtolower(trim((string) $row->unit))] ?? null;
            if ($factor === null) {
                $unknownUnits[] = (string) $row->unit;
                continue;
            }
            $feedKg = ($feedKg ?? 0) + (float) $row->qty * $factor;
        }
        if ($feedRows->isEmpty()) {
            $missing[] = 'feed';
        }
        if ($unknownUnits) {
            $missing[] = 'feed_unit';
        }
        if ($feedRows->sum('unpriced') > 0) {
            $missing[] = 'feed_prices';
        }

        // ── المال (نفس أرقام صفحة الربح) ──────────────────────────────────
        $totals = $this->profit->totals($flock);
        $totalCost = $totals['total_expenses'];
        $revenue = $totals['sales'];
        if ($totalCost <= 0) {
            $missing[] = 'costs';
        }

        $priced = $saleRows->filter(fn ($r) => (float) $r->net_amount > 0);
        if ($priced->count() < $saleRows->count()) {
            $missing[] = 'sale_prices';
        }
        $pricedKg = (float) $priced->sum('kg');
        $revenuePerKg = $pricedKg > 0 ? (float) $priced->sum('net_amount') / $pricedKg : null;

        $fcr = ($feedKg !== null && $feedKg > 0 && $liveKg) ? $feedKg / $liveKg : null;
        $adg = ($avgWeight !== null && $age > 0) ? $avgWeight * 1000 / $age : null;
        $epef = ($livabilityPct !== null && $avgWeight !== null && $fcr && $age > 0)
            ? ($livabilityPct * $avgWeight) / ($age * $fcr) * 100
            : null;

        $hasCost = $totalCost > 0;
        $costPerKg = ($hasCost && $liveKg) ? $totalCost / $liveKg : null;
        $marginPerKg = ($revenuePerKg !== null && $costPerKg !== null) ? $revenuePerKg - $costPerKg : null;

        // ما لم يُسترَد بعد من التكلفة؛ سعر التعادل = ما يلزم للكيلو من الطيور الباقية لتغطيته
        $unrecovered = max($totalCost - $revenue, 0);
        $breakEven = ($hasCost && $remaining > 0 && $remainingKg) ? $unrecovered / $remainingKg : null;

        return [
            'flock' => [
                'id' => $flock->id,
                'name' => $flock->name,
                'status' => $flock->status,
                'start_date' => $flock->start_date?->format('Y-m-d'),
                'close_date' => $flock->close_date?->format('Y-m-d'),
            ],
            'initial_birds' => $initial,
            'mortality' => $mortality,
            'mortality_pct' => $this->r($mortalityPct),
            'livability_pct' => $this->r($livabilityPct),
            'birds_sold' => $birdsSold,
            'birds_remaining' => $remaining,
            'sold_weight_kg' => $this->r($soldKg, 3),
            'avg_weight_kg' => $this->r($avgWeight, 3),
            'current_weight_kg' => $this->r($currentWeight, 3),
            'estimated_remaining_kg' => $this->r($remainingKg, 3),
            'live_weight_kg' => $this->r($liveKg, 3),
            'age_days' => $age,
            'feed_kg' => $this->r($feedKg, 3),
            'feed_unknown_units' => array_values(array_unique($unknownUnits)),
            'fcr' => $this->r($fcr, 3),
            'adg_g' => $this->r($adg, 1),
            'epef' => $this->r($epef, 0),
            'total_cost' => $this->r($totalCost),
            'revenue' => $this->r($revenue),
            'cost_per_bird' => ($hasCost && $initial > 0) ? $this->r($totalCost / $initial, 4) : null,
            'cost_per_kg' => $this->r($costPerKg, 4),
            'feed_cost' => $this->r($feedCost),
            'feed_cost_share_pct' => ($hasCost && $feedCost > 0) ? $this->r($feedCost / $totalCost * 100) : null,
            'revenue_per_kg' => $this->r($revenuePerKg, 4),
            'margin_per_kg' => $this->r($marginPerKg, 4),
            'unrecovered_cost' => $this->r($unrecovered),
            'cost_per_live_bird' => ($hasCost && $remaining > 0) ? $this->r($unrecovered / $remaining, 4) : null,
            'break_even_price_per_kg' => $this->r($breakEven, 4),
            'missing' => array_map(fn ($code) => ['code' => $code, 'message' => self::MESSAGES[$code]], array_values(array_unique($missing))),
        ];
    }

    /** نفس حساب العمر في تقرير الفوج: من البدء حتى الإغلاق (أو نفاد آخر طير) أو اليوم */
    private function ageDays(Flock $flock, int $remaining): int
    {
        if ($flock->current_age_days !== null) {
            return (int) $flock->current_age_days;
        }
        if (! $flock->start_date) {
            return 0;
        }
        $end = $flock->close_date ? Carbon::parse($flock->close_date) : Carbon::now();
        if (! $flock->close_date && $remaining <= 0) {
            $zero = (new ResolveFlockZeroDateAction)->execute($flock);
            if ($zero && $zero->lessThan($end)) {
                $end = $zero;
            }
        }

        return (int) Carbon::parse($flock->start_date)->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
    }

    private function r(?float $value, int $precision = 2): ?float
    {
        return $value === null ? null : round($value, $precision);
    }
}
