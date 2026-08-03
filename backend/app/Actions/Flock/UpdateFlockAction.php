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
                
                // Sync the "شراء كتاكيت" expense record
                $category = \App\Models\ExpenseCategory::firstOrCreate(
                    ['name' => 'شراء كتاكيت', 'farm_id' => null],
                    ['is_system' => true, 'is_active' => true, 'created_by' => $userId, 'updated_by' => $userId]
                );
                
                $expense = \App\Models\Expense::where('flock_id', $flock->id)->where('expense_category_id', $category->id)->first();
                
                if ($data['total_chick_cost'] > 0) {
                    if ($expense) {
                        $expense->update([
                            'quantity'         => $initialCount,
                            'unit_price'       => $unitPrice,
                            'total_amount'     => $data['total_chick_cost'],
                            'remaining_amount' => max(0, $data['total_chick_cost'] - $expense->paid_amount),
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
                            'total_amount'        => $data['total_chick_cost'],
                            'paid_amount'         => 0,
                            'remaining_amount'    => $data['total_chick_cost'],
                            'payment_status'      => 'unpaid',
                            'created_by'          => $userId,
                            'updated_by'          => $userId,
                        ]);
                    }
                } elseif ($expense) {
                    $expense->delete();
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
                    $totalSales = $flock->sales()->sum('net_amount');

                    // 1. Operational Expenses (manual entry). Purchase-debt expenses (linked to an
                    // inventory purchase) are excluded — their cost is already counted below via
                    // $inventoryCost once the stock is consumed; counting both would double it.
                    $opExpenses = $flock->expenses()->whereNull('linked_inventory_transaction_id')->sum('total_amount');

                    // 2. Chick Cost (initial investment)
                    $chickCost = (float) $flock->total_chick_cost;
                    
                    // 3. Inventory Consumption (feed, medicine, etc.)
                    $inventoryCost = (float) \App\Models\InventoryTransaction::where('flock_id', $flock->id)
                        ->where('direction', 'out')
                        ->where('transaction_type', 'consumption')
                        ->sum('total_amount');

                    // 4. Water Cost (Tankers)
                    $waterCost = (float) \App\Models\FlockWaterLog::where('flock_id', $flock->id)->sum('total_amount');

                    // $opExpenses already includes the "شراء كتاكيت" expense record — do NOT add $chickCost
                    $totalExpenses = $opExpenses + $inventoryCost + $waterCost;
                    
                    $netProfit = $totalSales - $totalExpenses;
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

                    $payableShares = $activeShares->filter(fn ($s) => (float) $s->share_percent > 0)->values();
                    $shareCount = $payableShares->count();
                    $distributed = 0.0;

                    foreach ($payableShares as $index => $share) {
                        $percent = (float) $share->share_percent;

                        // Round every partner's cut to the cent; the last partner absorbs whatever
                        // rounding remainder is left so the sum of all cuts always equals
                        // $amountToDistribute exactly (no pennies silently created or lost).
                        $partnerAmount = ($index === $shareCount - 1)
                            ? round($amountToDistribute - $distributed, 2)
                            : round($amountToDistribute * ($percent / 100), 2);
                        $distributed += $partnerAmount;

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

        $blockingCount = $unpaidExpenses + $unpaidSales + $missingPriceExpenses + $missingPriceSales + $missingPriceInventory;

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
