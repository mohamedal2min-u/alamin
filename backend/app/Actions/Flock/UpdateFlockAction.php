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
            // قفل صف الفوج: إغلاقان متزامنان لا يوزّعان الأرباح مرتين، وحالته تُقرأ من القاعدة لا من الذاكرة
            $flock = Flock::whereKey($flock->id)->lockForUpdate()->firstOrFail();

            // الفوج المغلق/الملغى لا يُعدَّل إلا ملاحظاته — يُطبَّق قبل أي تعديل مالي (تكلفة الكتاكيت)
            if (in_array($flock->status, ['closed', 'cancelled'], true)) {
                // (status يبقى ليُرفض الانتقال من حالة نهائية برسالة واضحة)
                $data = array_intersect_key($data, ['notes' => true, 'status' => true]);
            }

            // لا يجوز خفض العدد الأولي دون ما بيع ونفق فعلاً
            if (isset($data['initial_count'])) {
                $consumed = (int) $flock->mortalities()->sum('quantity') + (int) $flock->saleItems()->sum('birds_count');
                if ((int) $data['initial_count'] < $consumed) {
                    throw new \Exception("العدد الأولي لا يمكن أن يقل عن ما بيع ونفق فعلاً ({$consumed})", 422);
                }
            }

            // مزامنة مصروف «شراء كتاكيت» فقط عند تغيّر العدد أو السعر فعلاً.
            // السعر يضعه المحاسب على المصروف نفسه (سعر الفوج يبقى فارغاً عادةً)، فلا يُحذف المصروف ولا دفعاته أبداً هنا.
            $countChanged = isset($data['initial_count']) && (int) $data['initial_count'] !== (int) $flock->initial_count;
            $priceGiven = array_key_exists('chick_unit_price', $data) && $data['chick_unit_price'] !== null;

            if ($countChanged || $priceGiven) {
                $category = \App\Models\ExpenseCategory::firstOrCreate(
                    ['name' => 'شراء كتاكيت', 'farm_id' => null],
                    ['is_system' => true, 'is_active' => true, 'created_by' => $userId, 'updated_by' => $userId]
                );
                $expense = \App\Models\Expense::where('flock_id', $flock->id)->where('expense_category_id', $category->id)->first();

                $initialCount = (int) ($data['initial_count'] ?? $flock->initial_count);
                $unitPrice = $priceGiven ? (float) $data['chick_unit_price']
                    : (($expense && $expense->unit_price > 0) ? (float) $expense->unit_price
                    : ($flock->chick_unit_price !== null ? (float) $flock->chick_unit_price : null));
                $total = $unitPrice !== null ? round($unitPrice * $initialCount, 2) : null;
                $data['total_chick_cost'] = $total;

                if ($expense) {
                    $paid = (float) $expense->paid_amount;
                    if ($total !== null && $paid > $total + 0.001) {
                        throw new \Exception('المدفوع لمصروف الكتاكيت ('.$paid.') أكبر من التكلفة الجديدة ('.$total.')', 422);
                    }
                    $expense->update([
                        'quantity'         => $initialCount,
                        'unit_price'       => $unitPrice,
                        'total_amount'     => $total ?? 0,
                        'remaining_amount' => $total !== null ? max(0, $total - $paid) : 0,
                        'payment_status'   => $total > 0 && $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
                        'updated_by'       => $userId,
                    ]);
                } else {
                    \App\Models\Expense::create([
                        'farm_id'             => $flock->farm_id,
                        'flock_id'            => $flock->id,
                        'expense_category_id' => $category->id,
                        'entry_date'          => $flock->start_date,
                        'description'         => 'تكلفة شراء كتاكيت — ' . $flock->name,
                        'quantity'            => $initialCount,
                        'unit_price'          => $unitPrice,
                        'total_amount'        => $total ?? 0,
                        'paid_amount'         => 0,
                        'remaining_amount'    => $total ?? 0,
                        'payment_status'      => 'unpaid',
                        'created_by'          => $userId,
                        'updated_by'          => $userId,
                    ]);
                }
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

                // ======= Block closure if financial records are incomplete =======
                if ($data['status'] === 'closed') {
                    $this->assertNoBlockingRecords($flock);
                }

                // ======= Profit/Loss Distribution on Closure =======
                if ($data['status'] === 'closed') {
                    // المعادلة نفسها التي يعرضها المحاسب مباشرة (FlockProfitService)
                    $profit = app(\App\Services\FlockProfitService::class);
                    $totals = $profit->totals($flock);
                    $totalSales = $totals['sales'];
                    $totalExpenses = $totals['total_expenses'];

                    $netProfit = $totals['net'];
                    $transactionType = $netProfit >= 0 ? 'profit' : 'loss';
                    $amountToDistribute = abs($netProfit);

                    $activeShares = \App\Models\FarmPartnerShare::where('farm_id', $flock->farm_id)
                        ->where('is_active', true)
                        ->get();

                    // Only enforce the 100% check if this farm actually uses the partner-shares
                    // feature (some active share exists). Farms that never set up partners at all
                    // keep their original behavior of skipping distribution entirely — we only
                    // want to block closure for the actual bug case: shares exist but don't sum to
                    // 100%, which used to silently distribute only part of the profit/loss and
                    // leave the rest with no accounting record anywhere.
                    if ($activeShares->isNotEmpty()) {
                        $sharesTotal = round((float) $activeShares->sum('share_percent'), 2);
                        if (abs($sharesTotal - 100.0) > 0.01) {
                            throw new \Exception(
                                "لا يمكن إغلاق الفوج — مجموع نسب حصص الشركاء الفعالة ({$sharesTotal}%) لا يساوي 100%. راجع صفحة الشركاء أولاً.",
                                422
                            );
                        }
                    }

                    foreach ($profit->split($flock->farm_id, $amountToDistribute) as $cut) {
                        $percent = $cut['percent'];
                        $partnerAmount = $cut['amount'];
                        $share = (object) ['partner_id' => $cut['partner_id']];

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
                // ======= Stock Carry-Over / Settlement on Closure =======
                if ($data['status'] === 'closed' && ($data['stock_action'] ?? 'transfer') === 'settle') {
                    $this->settleRemainingStock($flock, $userId, $data['close_date'] ?? now()->toDateString());
                }
                // If stock_action = 'transfer' (default): do nothing — stock stays in warehouse for next flock.
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

    /**
     * @throws \Exception code 422 when blocking financial records exist
     *
     * Blocking conditions:
     *   - expenses/sales with payment_status unpaid or partial
     *   - expenses with quantity=0/null, unit_price=0/null, or total_amount<=0
     *   - sales with net_amount<=0
     */
    private function assertNoBlockingRecords(Flock $flock): void
    {
        // Expenses only ever write 'unpaid'/'partial'/'paid' (the legacy 'debt' value was
        // normalized away — see 2026_04_18_000525_normalize_expenses_payment_status.php).
        $unpaidExpenses = $flock->expenses()
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->count();

        // Sales use 'debt' (not 'unpaid') as their unpaid value — see CreateSaleAction/
        // UpdateSalePaymentAction and the chk_sales_payment_status CHECK constraint.
        $unpaidSales = $flock->sales()
            ->whereIn('payment_status', ['debt', 'partial'])
            ->count();

        // All expenses (even paid ones) block if missing unit_price/quantity details
        $missingPriceExpenses = $flock->expenses()
            ->where(function ($q): void {
                $q->whereNull('quantity')
                  ->orWhere('quantity', '<=', 0)
                  ->orWhereNull('unit_price')
                  ->orWhere('unit_price', '<=', 0)
                  ->orWhere('total_amount', '<=', 0);
            })
            ->count();

        $missingPriceSales = $flock->sales()
            ->where('net_amount', '<=', 0)
            ->count();

        $missingPriceInventory = \App\Models\InventoryTransaction::where('flock_id', $flock->id)
            ->where('transaction_type', 'consumption')
            ->where(function ($q): void {
                $q->whereNull('original_quantity')
                  ->orWhere('original_quantity', '<=', 0)
                  ->orWhereNull('unit_price')
                  ->orWhere('unit_price', '<=', 0)
                  ->orWhere('total_amount', '<=', 0);
            })
            ->count();

        // الماء يدخل في الربح، فبلا سعر أو بلا تسديد يمنع الإغلاق مثل المصاريف
        $blockingWater = $flock->waterLogs()
            ->where(function ($q): void {
                $q->whereNull('total_amount')
                  ->orWhere('total_amount', '<=', 0)
                  ->orWhereNull('payment_status')
                  ->orWhereIn('payment_status', ['unpaid', 'partial']);
            })
            ->count();

        $blockingCount = $unpaidExpenses + $unpaidSales + $missingPriceExpenses + $missingPriceSales + $missingPriceInventory + $blockingWater;

        if ($blockingCount > 0) {
            throw new \Exception(
                json_encode([
                    'message'        => 'لا يمكن إغلاق الفوج — يوجد ' . $blockingCount . ' سجل مالي غير مكتمل أو غير مسدد.',
                    'blocking_count' => $blockingCount,
                    'review_url'     => '/accounting?tab=review&filter=blocking&flock_id=' . $flock->id,
                ]),
                422
            );
        }
    }

    /**
     * Write off remaining farm stock as a loss expense on flock closure.
     * Creates one "adjustment" inventory transaction per item with remaining stock.
     */
    private function settleRemainingStock(Flock $flock, int $userId, string $closeDate): void
    {
        $warehouseItems = \App\Models\WarehouseItem::with('item')
            ->whereHas('warehouse', fn ($q) => $q->where('farm_id', $flock->farm_id))
            ->where('current_quantity', '>', 0)
            ->get();

        foreach ($warehouseItems as $wi) {
            if ((float) $wi->current_quantity <= 0) continue;

            \App\Models\InventoryTransaction::create([
                'farm_id'          => $flock->farm_id,
                'flock_id'         => $flock->id,
                'item_id'          => $wi->item_id,
                'warehouse_id'     => $wi->warehouse_id,
                'transaction_date' => $closeDate,
                'transaction_type' => 'adjustment',
                'direction'        => 'out',
                'original_quantity'=> (float) $wi->current_quantity,
                'computed_quantity'=> (float) $wi->current_quantity,
                'unit_price'       => null,
                'total_amount'     => null,
                'notes'            => 'تسوية مخزون عند إغلاق الفوج: ' . $flock->name,
                'created_by'       => $userId,
            ]);

            // Zero out warehouse item stock
            $wi->update(['current_quantity' => 0]);
        }
    }
}
