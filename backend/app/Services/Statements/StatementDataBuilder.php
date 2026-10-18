<?php

namespace App\Services\Statements;

use App\Models\Farm;
use App\Models\Flock;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Models\Sale;
use App\Services\FlockProfitService;
use App\Services\ReviewQueueService;

/**
 * يجمع أرقام الكشوف (فوج، شريك، ورقة وزن) كمصفوفات جاهزة للعرض.
 * منفصل عن توليد PDF ليُختبر وحده؛ أرقام الربح والحصص من FlockProfitService حصراً.
 */
class StatementDataBuilder
{
    public const TYPE_LABELS = [
        'deposit' => 'إيداع',
        'withdraw' => 'سحب',
        'profit' => 'ربح',
        'loss' => 'خسارة',
        'settlement' => 'تسوية',
        'adjustment' => 'تعديل',
    ];

    public function __construct(
        private readonly FlockProfitService $profit,
        private readonly ReviewQueueService $reviewQueue,
    ) {}

    /** كشف ربح/خسارة الفوج للشركاء */
    public function flock(Flock $flock): array
    {
        $summary = $this->profit->summary($flock);
        $breakdown = $this->profit->costBreakdown($flock);

        $sales = $flock->sales()->with('saleItems')->orderBy('sale_date')->orderBy('id')->get();
        $mortality = (int) $flock->mortalities()->sum('quantity');
        $soldBirds = (int) $sales->sum(fn (Sale $s) => $s->saleItems->sum('birds_count'));
        $soldWeight = round((float) $sales->sum(fn (Sale $s) => $s->saleItems->sum('total_weight_kg')), 3);

        $queue = $this->reviewQueue->getQueue($flock->farm_id, ['reason' => 'missing_price', 'flock_id' => $flock->id, 'per_page' => 1]);

        $endDate = $flock->close_date ?? now('Asia/Damascus');

        return [
            'farm_name' => $this->farmName($flock->farm_id),
            'flock' => [
                'id' => $flock->id,
                'name' => $flock->name,
                'status' => $flock->status,
                'start_date' => $flock->start_date?->toDateString(),
                'close_date' => $flock->close_date?->toDateString(),
                'age_days' => $flock->start_date ? (int) $flock->start_date->diffInDays($endDate) : null,
                'initial_count' => (int) $flock->initial_count,
                'mortality' => $mortality,
                'mortality_rate' => $flock->initial_count > 0 ? round($mortality / $flock->initial_count * 100, 2) : 0.0,
                'sold_birds' => $soldBirds,
                'sold_weight_kg' => $soldWeight,
                'avg_weight_kg' => $soldBirds > 0 ? round($soldWeight / $soldBirds, 3) : null,
                'remaining' => (int) $flock->initial_count - $mortality - $soldBirds,
            ],
            'sales' => $sales->map(fn (Sale $s) => [
                'date' => $s->sale_date?->toDateString(),
                'buyer' => $s->buyer_name,
                'birds' => (int) $s->saleItems->sum('birds_count'),
                'weight_kg' => round((float) $s->saleItems->sum('total_weight_kg'), 3),
                'amount' => (float) $s->net_amount,
            ])->all(),
            'revenue' => $summary['sales'],
            'costs' => $breakdown,
            'total_expenses' => $summary['total_expenses'],
            'net' => $summary['net'],
            'partners' => $summary['partners'],
            'balances' => $summary['balances'],
            'shares_total' => $summary['shares_total'],
            'recorded' => $summary['recorded'],
            'unpriced_count' => (int) ($queue['meta']['total'] ?? 0),
        ];
    }

    /** كشف حساب الشريك: حركاته برصيد تراكمي، ورصيده لكل فوج ولكل الأفواج */
    public function partner(Partner $partner): array
    {
        $sign = FlockProfitService::BALANCE_SIGN;
        $txns = PartnerTransaction::where('farm_id', $partner->farm_id)
            ->where('partner_id', $partner->id)
            ->with('flock:id,name')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $running = 0.0;
        $rows = $txns->map(function (PartnerTransaction $t) use (&$running, $sign) {
            // «تعديل» بلا اتجاه ثابت فلا يدخل الرصيد (نفس صيغة FlockProfitService::balances)
            $signed = isset($sign[$t->transaction_type]) ? $sign[$t->transaction_type] * (float) $t->amount : 0.0;
            $running = round($running + $signed, 2);

            return [
                'date' => $t->transaction_date?->toDateString(),
                'type' => $t->transaction_type,
                'type_label' => self::TYPE_LABELS[$t->transaction_type] ?? $t->transaction_type,
                'flock' => $t->flock?->name,
                'description' => $t->description,
                'amount' => (float) $t->amount,
                'signed_amount' => round($signed, 2),
                'counted' => isset($sign[$t->transaction_type]),
                'balance' => $running,
            ];
        })->all();

        $counted = $txns->filter(fn ($t) => isset($sign[$t->transaction_type]));
        $perFlock = $counted->groupBy(fn ($t) => (int) $t->flock_id)->map(fn ($group, $flockId) => [
            'flock_id' => $flockId ?: null,
            'flock' => $flockId ? $group->first()->flock?->name : null,
            'balance' => round($group->sum(fn ($t) => $sign[$t->transaction_type] * (float) $t->amount), 2),
        ])->sortBy(fn ($r) => $r['flock_id'] ?? 0)->values()->all();

        return [
            'farm_name' => $this->farmName($partner->farm_id),
            'partner' => ['id' => $partner->id, 'name' => $partner->name],
            'transactions' => $rows,
            'per_flock' => $perFlock,
            'total_balance' => $running,
        ];
    }

    /** ورقة وزن (وصل قبان) لعملية بيع واحدة */
    public function sale(Sale $sale): array
    {
        $sale->loadMissing('saleItems', 'flock:id,name');

        $lines = $sale->saleItems->map(function ($i) {
            $net = (float) $i->total_weight_kg;
            $gross = $i->gross_weight_kg !== null ? (float) $i->gross_weight_kg : null;
            $tare = $gross !== null
                ? round($gross - $net, 3)
                : ($i->crates_count && $i->crate_weight_kg ? round($i->crates_count * (float) $i->crate_weight_kg, 3) : null);
            $price = $i->unit_price_per_kg !== null && (float) $i->unit_price_per_kg > 0 ? (float) $i->unit_price_per_kg : null;

            return [
                'birds' => (int) $i->birds_count,
                'crates' => $i->crates_count !== null ? (int) $i->crates_count : null,
                'gross_kg' => $gross,
                'tare_kg' => $tare,
                'net_kg' => round($net, 3),
                'price_per_kg' => $price,
                'line_total' => $price !== null ? (float) $i->line_total : null,
            ];
        });

        $priced = $lines->every(fn ($l) => $l['price_per_kg'] !== null) && (float) $sale->net_amount > 0;

        return [
            'farm_name' => $this->farmName($sale->farm_id),
            'sale' => [
                'id' => $sale->id,
                'reference_no' => $sale->reference_no,
                'date' => $sale->sale_date?->toDateString(),
                'buyer' => $sale->buyer_name,
                'flock' => $sale->flock?->name,
                'vehicle_before_kg' => $sale->vehicle_weight_before_kg !== null ? (float) $sale->vehicle_weight_before_kg : null,
                'vehicle_after_kg' => $sale->vehicle_weight_after_kg !== null ? (float) $sale->vehicle_weight_after_kg : null,
                'deduction_kg' => $sale->weight_deduction_kg !== null ? (float) $sale->weight_deduction_kg : null,
            ],
            'lines' => $lines->all(),
            'totals' => [
                'birds' => (int) $lines->sum('birds'),
                'crates' => (int) $lines->sum('crates'),
                'gross_kg' => round((float) $lines->sum('gross_kg'), 3),
                'tare_kg' => round((float) $lines->sum('tare_kg'), 3),
                'net_kg' => round((float) $lines->sum('net_kg'), 3),
            ],
            'priced' => $priced,
            'gross_amount' => (float) $sale->gross_amount,
            'discount_amount' => (float) $sale->discount_amount,
            'net_amount' => (float) $sale->net_amount,
            'received_amount' => (float) $sale->received_amount,
            'remaining_amount' => (float) $sale->remaining_amount,
        ];
    }

    private function farmName(int $farmId): string
    {
        return (string) Farm::whereKey($farmId)->value('name');
    }
}
