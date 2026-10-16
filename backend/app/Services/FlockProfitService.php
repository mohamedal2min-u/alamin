<?php

namespace App\Services;

use App\Models\FarmPartnerShare;
use App\Models\Flock;
use App\Models\FlockWaterLog;
use App\Models\InventoryTransaction;
use App\Models\PartnerTransaction;

/**
 * ربح/خسارة الفوج وحصة كل شريك.
 * مصدر واحد للمعادلة: يستخدمه إغلاق الفوج (التوزيع الفعلي) وعرض الربح المباشر للمحاسب،
 * فلا يختلف الرقم المعروض عن الرقم الذي يُسجَّل عند الإغلاق.
 */
class FlockProfitService
{
    /**
     * @return array{sales: float, operations: float, inventory: float, water: float, total_expenses: float, net: float}
     */
    public function totals(Flock $flock): array
    {
        $sales = (float) $flock->sales()->sum('net_amount');

        // مصاريف التشغيل (الإدخال اليدوي). ديون شراء المخزون مستثناة: تكلفتها تُحسب عند الاستهلاك أدناه
        $operations = (float) $flock->expenses()->whereNull('linked_inventory_transaction_id')->sum('total_amount');

        // استهلاك المخزون (علف، دواء...)
        $inventory = (float) InventoryTransaction::where('flock_id', $flock->id)
            ->where('direction', 'out')
            ->where('transaction_type', 'consumption')
            ->sum('total_amount');

        // الماء (صهاريج)
        $water = (float) FlockWaterLog::where('flock_id', $flock->id)->sum('total_amount');

        // مصاريف التشغيل تشمل أصلاً قيد «شراء كتاكيت»، فلا يُضاف total_chick_cost
        $totalExpenses = $operations + $inventory + $water;

        return [
            'sales' => $sales,
            'operations' => $operations,
            'inventory' => $inventory,
            'water' => $water,
            'total_expenses' => $totalExpenses,
            'net' => $sales - $totalExpenses,
        ];
    }

    /**
     * حصص الشركاء الفعالة ذات النسبة الموجبة، مع مبلغ كل شريك من $amount.
     * كل حصة تُقرَّب للسنت، والشريك الأخير يأخذ فرق التقريب ليبقى المجموع مساوياً للمبلغ بالضبط.
     *
     * @return array<int, array{partner_id: int, percent: float, amount: float}>
     */
    public function split(int $farmId, float $amount): array
    {
        $shares = $this->activeShares($farmId)->filter(fn ($s) => (float) $s->share_percent > 0)->values();
        $count = $shares->count();
        $distributed = 0.0;
        $out = [];

        foreach ($shares as $index => $share) {
            $percent = (float) $share->share_percent;
            $partnerAmount = ($index === $count - 1)
                ? round($amount - $distributed, 2)
                : round($amount * ($percent / 100), 2);
            $distributed += $partnerAmount;
            $out[] = ['partner_id' => (int) $share->partner_id, 'percent' => $percent, 'amount' => $partnerAmount];
        }

        return $out;
    }

    public function activeShares(int $farmId)
    {
        return FarmPartnerShare::where('farm_id', $farmId)->where('is_active', true)->get();
    }

    /**
     * ملخص للعرض: الربح الحالي وحصة كل شريك.
     * الفوج المغلق: المبالغ المسجّلة فعلاً في حسابات الشركاء عند الإغلاق.
     */
    public function summary(Flock $flock): array
    {
        $totals = $this->totals($flock);
        $shares = $this->activeShares($flock->farm_id)->load('partner');
        $names = $shares->mapWithKeys(fn ($s) => [(int) $s->partner_id => $s->partner?->name]);

        $recorded = PartnerTransaction::where('flock_id', $flock->id)
            ->whereIn('transaction_type', ['profit', 'loss'])
            ->with('partner')
            ->get();

        if ($flock->status === 'closed' && $recorded->isNotEmpty()) {
            $partners = $recorded->groupBy('partner_id')->map(function ($rows, $partnerId) {
                $first = $rows->first();
                $sign = $first->transaction_type === 'loss' ? -1 : 1;

                return [
                    'partner_id' => (int) $partnerId,
                    'name' => $first->partner?->name,
                    'percent' => (float) ($first->metadata['applied_share_percent'] ?? 0),
                    'amount' => $sign * (float) $rows->sum('amount'),
                ];
            })->values()->all();
            $isRecorded = true;
        } else {
            $sign = $totals['net'] < 0 ? -1 : 1;
            $partners = array_map(fn ($p) => [
                'partner_id' => $p['partner_id'],
                'name' => $names[$p['partner_id']] ?? null,
                'percent' => $p['percent'],
                'amount' => $sign * $p['amount'],
            ], $this->split($flock->farm_id, abs($totals['net'])));
            $isRecorded = false;
        }

        return [
            'flock' => ['id' => $flock->id, 'name' => $flock->name, 'status' => $flock->status],
            ...$totals,
            'shares_total' => round((float) $shares->sum('share_percent'), 2),
            'partners' => $partners,
            'recorded' => $isRecorded,
        ];
    }
}
