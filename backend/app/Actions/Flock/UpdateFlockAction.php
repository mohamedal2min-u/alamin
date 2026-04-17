<?php

namespace App\Actions\Flock;

use App\Models\Flock;
use Illuminate\Support\Facades\DB;

class UpdateFlockAction
{
    // الانتقالات المسموح بها بين الحالات
    private const ALLOWED_TRANSITIONS = [
        'draft'  => ['active', 'cancelled'],
        'active' => ['closed', 'cancelled'],
        // closed و cancelled حالات نهائية
    ];

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws \Exception إذا كانت الحالة النهائية أو الانتقال غير مسموح
     */
    public function execute(Flock $flock, int $userId, array $data): Flock
    {
        return DB::transaction(function () use ($flock, $userId, $data): Flock {
            // Recalculate total chick cost if price or count is changed
            $initialCount = $data['initial_count'] ?? $flock->initial_count;
            $unitPrice    = array_key_exists('chick_unit_price', $data) ? $data['chick_unit_price'] : $flock->chick_unit_price;
            
            if (isset($data['chick_unit_price']) || isset($data['initial_count'])) {
                $data['total_chick_cost'] = ($unitPrice !== null && $initialCount !== null) ? ($unitPrice * $initialCount) : null;
            }

            // ── التحقق من الانتقال الإذا تغيّرت الحالة ─────────────────────
            if (isset($data['status']) && $data['status'] !== $flock->status) {
                $this->validateTransition($flock->status, $data['status']);

                // إذا أُغلق الفوج: سجّل تاريخ الإغلاق
                if (in_array($data['status'], ['closed', 'cancelled'])) {
                    $data['close_date'] = $data['close_date'] ?? now()->toDateString();
                }

                // إذا عُكس من draft إلى active: تحقق من عدم وجود فوج نشط آخر
                if ($data['status'] === 'active') {
                    $hasActive = Flock::where('farm_id', $flock->farm_id)
                        ->where('status', 'active')
                        ->where('id', '!=', $flock->id)
                        ->exists();

                    if ($hasActive) {
                        throw new \Exception(
                            'توجد مزرعة بها فوج نشط بالفعل. أغلق الفوج الحالي أولاً.',
                            422
                        );
                    }
                }

                // ======= Profit/Loss Distribution on Closure =======
                if ($data['status'] === 'closed') {
                    $totalSales = $flock->sales()->sum('net_amount');
                    
                    // 1. Operational Expenses (manual entry)
                    $opExpenses = $flock->expenses()->sum('total_amount');
                    
                    // 2. Chick Cost (initial investment)
                    $chickCost = (float) $flock->total_chick_cost;
                    
                    // 3. Inventory Consumption (feed, medicine, etc.)
                    $inventoryCost = (float) \App\Models\InventoryTransaction::where('flock_id', $flock->id)
                        ->where('direction', 'out')
                        ->where('transaction_type', 'consumption')
                        ->sum('total_amount');

                    $totalExpenses = $opExpenses + $chickCost + $inventoryCost;
                    
                    $netProfit = $totalSales - $totalExpenses;
                    $transactionType = $netProfit >= 0 ? 'profit' : 'loss';
                    $amountToDistribute = abs($netProfit);

                    $activeShares = \App\Models\FarmPartnerShare::where('farm_id', $flock->farm_id)
                        ->where('is_active', true)
                        ->get();

                    foreach ($activeShares as $share) {
                        $percent = (float) $share->share_percent;
                        if ($percent > 0) {
                            $partnerAmount = $amountToDistribute * ($percent / 100);
                            \App\Models\PartnerTransaction::create([
                                'farm_id' => $flock->farm_id,
                                'partner_id' => $share->partner_id,
                                'flock_id' => $flock->id,
                                'transaction_date' => $data['close_date'] ?? now()->toDateString(),
                                'transaction_type' => $transactionType,
                                'amount' => $partnerAmount,
                                'description' => ($transactionType === 'profit' ? 'أرباح' : 'خسائر') . ' الفوج: ' . $flock->name,
                                'created_by' => $userId,
                                'metadata' => [
                                    'applied_share_percent' => $percent,
                                    'flock_total_net' => $netProfit,
                                    'flock_total_sales' => $totalSales,
                                    'flock_total_expenses' => $totalExpenses
                                ]
                            ]);
                        }
                    }
                }
                // ===================================================
            }

            // ── الفوج المغلق/الملغى: لا يُعدَّل إلا الملاحظات ──────────────
            if (in_array($flock->status, ['closed', 'cancelled'])) {
                $data = array_intersect_key($data, ['notes' => true]);
            }

            $flock->update(array_merge($data, ['updated_by' => $userId]));

            return $flock->fresh();
        });
    }

    private function validateTransition(string $from, string $to): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$from] ?? [];

        if (! in_array($to, $allowed)) {
            throw new \Exception(
                "لا يمكن الانتقال من حالة «{$from}» إلى «{$to}»",
                422
            );
        }
    }
}
