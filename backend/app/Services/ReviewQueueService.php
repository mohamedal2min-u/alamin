<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\FlockWaterLog;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Sale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReviewQueueService
{
    public function __construct(private readonly PriceAuditService $priceAudit) {}

    /**
     * @param  array{type?:string, reason?:string, flock_id?:int|string, page?:int, per_page?:int}  $filters
     */
    public function getQueue(int $farmId, array $filters = []): array
    {
        $type = $filters['type'] ?? 'all';
        $reason = $filters['reason'] ?? null;
        $flockId = isset($filters['flock_id']) && $filters['flock_id'] !== '' ? (int) $filters['flock_id'] : null;
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));

        $rows = $this->fetchRows($farmId, $type, $flockId);

        // Attach reasons then keep only qualifying rows
        $rows = $rows->map(fn ($row) => $this->attachReasons($row));
        $qualifying = $rows->filter(fn ($row) => count($row['review_reasons']) > 0);

        // scope=all: كل السجلات بما فيها المسعّرة والمدفوعة، ليتمكن المحاسب من تعديل سعر موضوع مسبقاً
        $listAll = ($filters['scope'] ?? null) === 'all';
        $rows = $listAll ? $rows : $qualifying;

        // Filter by specific reason if requested
        if ($reason) {
            $rows = $rows->filter(fn ($row) => in_array($reason, $row['review_reasons']));
            $qualifying = $qualifying->filter(fn ($row) => in_array($reason, $row['review_reasons']));
        }

        $rows = $rows->values();

        // scope=all: ملخص الفئات يشمل كل السجلات (المسعّرة وغير المسعّرة) ليرى المحاسب الإجماليات الكاملة
        $summary = $this->buildSummary($listAll ? $rows : $rows, $listAll);

        // Filter by category if requested (must match the same fallback key used in buildSummary)
        $category = $filters['category'] ?? null;
        if ($category) {
            $rows = $rows->filter(fn ($row) => ($row['category_code'] ?? 'uncategorized') === $category);
            $rows = $rows->values();
        }

        // Surface the most actionable rows first: real outstanding balances before
        // price-pending placeholders, flock-closure blockers before routine items,
        // oldest-pending within the same priority.
        $rows = $rows->sort(function (array $a, array $b): int {
            $remainingCmp = $b['remaining_amount'] <=> $a['remaining_amount'];
            if ($remainingCmp !== 0) {
                return $remainingCmp;
            }

            $blockingA = in_array('blocking_flock_closure', $a['review_reasons'], true) ? 0 : 1;
            $blockingB = in_array('blocking_flock_closure', $b['review_reasons'], true) ? 0 : 1;
            if ($blockingA !== $blockingB) {
                return $blockingA <=> $blockingB;
            }

            return ($a['entry_date'] ?? '') <=> ($b['entry_date'] ?? '');
        })->values();

        // Manual pagination after reason-filtering so summary always reflects full filtered set
        $total = $rows->count();
        $offset = ($page - 1) * $perPage;
        $pageItems = $this->withPriceHistory($farmId, $rows->slice($offset, $perPage)->values());

        return [
            'summary' => $summary,
            'data' => $pageItems->toArray(),
            'meta' => [
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
            ],
        ];
    }

    /**
     * حالة فوج السجل (لمنع التعديل على الأفواج المغلقة).
     */
    public function flockStatusOf(int $farmId, string $type, int $recordId): ?string
    {
        $record = match ($type) {
            'expense' => Expense::with('flock:id,status')->where('farm_id', $farmId)->find($recordId),
            'water_log' => FlockWaterLog::with('flock:id,status')->where('farm_id', $farmId)->find($recordId),
            'sale' => Sale::with('flock:id,status')->where('farm_id', $farmId)->find($recordId),
            default => null,
        };

        return $record?->flock?->status;
    }

    /**
     * Update a single record, recalculate financial fields, return fresh row with reasons.
     */
    public function updateRecord(int $farmId, string $type, int $recordId, array $data, ?int $userId = null): array
    {
        // كل التعديل في معاملة واحدة مع قفل السجل: لا كتابة جزئية ولا تعديلان متزامنان يضيّع أحدهما الآخر
        return DB::transaction(fn () => $this->applyUpdate($farmId, $type, $recordId, $data, $userId));
    }

    private function applyUpdate(int $farmId, string $type, int $recordId, array $data, ?int $userId): array
    {
        $priceBefore = null;
        $paidBefore = null;

        if ($type === 'expense') {
            $record = Expense::where('farm_id', $farmId)->lockForUpdate()->findOrFail($recordId);
            $priceBefore = $this->normalizeExpense($record)['unit_price'];
            $paidBefore = (float) ($record->paid_amount ?? 0);

            if (array_key_exists('unit_price', $data)) {
                $record->unit_price = $data['unit_price'];
                if ($record->quantity !== null && $data['unit_price'] !== null) {
                    $record->total_amount = $record->quantity * $data['unit_price'];
                }
            }

            if (array_key_exists('paid_amount', $data)) {
                $record->paid_amount = $data['paid_amount'];
            }

            $this->assertPaymentWithinTotal($data, (float) $record->total_amount, (float) $record->paid_amount);

            $record->remaining_amount = max(0, $record->total_amount - $record->paid_amount);
            $record->payment_status = $this->derivePaymentStatus(
                (float) $record->total_amount,
                (float) $record->paid_amount
            );

            $record->save();

            // الشحنة المرتبطة تأخذ السعر نفسه كي لا يختلف المخزون عن المحاسبة
            if (array_key_exists('unit_price', $data) && $record->linked_inventory_transaction_id) {
                $shipment = InventoryTransaction::where('farm_id', $farmId)->where('id', $record->linked_inventory_transaction_id)->first();
                if ($shipment) {
                    $shipment->update([
                        'unit_price' => $record->unit_price,
                        'total_amount' => $record->total_amount,
                    ]);
                    // متوسط تكلفة المخزون وما استُهلك قبل التسعير يتبعان السعر الجديد
                    app(InventoryRepricingService::class)->repriceItem($farmId, (int) $shipment->item_id);
                }
            }

            $row = $this->normalizeExpense($record->load(['flock', 'expenseCategory']));

        } elseif ($type === 'water_log') {
            $record = FlockWaterLog::where('farm_id', $farmId)->lockForUpdate()->findOrFail($recordId);
            $priceBefore = $this->normalizeWaterLog($record)['unit_price'];
            $paidBefore = (float) ($record->paid_amount ?? 0);

            if (array_key_exists('unit_price', $data)) {
                if ($record->quantity !== null && $data['unit_price'] !== null) {
                    $record->total_amount = $record->quantity * $data['unit_price'];
                }
            }

            if (array_key_exists('paid_amount', $data)) {
                $record->paid_amount = $data['paid_amount'];
            }

            $this->assertPaymentWithinTotal($data, (float) ($record->total_amount ?? 0), (float) ($record->paid_amount ?? 0));

            $record->payment_status = $this->derivePaymentStatus(
                (float) ($record->total_amount ?? 0),
                (float) ($record->paid_amount ?? 0)
            );

            $record->save();

            $row = $this->normalizeWaterLog($record->load('flock'));

        } elseif ($type === 'sale') {
            $record = Sale::where('farm_id', $farmId)->with('saleItems')->lockForUpdate()->findOrFail($recordId);
            $priceBefore = $this->normalizeSale($record)['unit_price'];
            $paidBefore = (float) ($record->received_amount ?? 0);

            // سعر الكيلو المُدخَل هنا يُطبَّق على كل سطور البيع (أول تسعير أو تعديل لاحق؛
            // التعديل يُسجَّل في سجل الأسعار والسعر القديم يبقى ظاهراً)، ثم يُعاد حساب الإجمالي.
            if (array_key_exists('unit_price', $data) && $data['unit_price'] !== null) {
                foreach ($record->saleItems as $saleItem) {
                    $saleItem->unit_price_per_kg = $data['unit_price'];
                    $saleItem->line_total = round($saleItem->total_weight_kg * $data['unit_price'], 2);
                    $saleItem->save();
                }

                $record->gross_amount = $record->saleItems()->sum('line_total');
                $record->net_amount = max(0, round($record->gross_amount - $record->discount_amount, 2));
            }

            if (array_key_exists('paid_amount', $data)) {
                $record->received_amount = $data['paid_amount'];
            }

            $netAmount = (float) $record->net_amount;
            $received = (float) $record->received_amount;
            $this->assertPaymentWithinTotal($data, $netAmount, $received);
            $remaining = max(0, round($netAmount - $received, 2));

            $record->remaining_amount = $remaining;
            // Sales only allow 'paid' | 'partial' | 'debt' (never 'unpaid' — see sales table
            // CHECK constraint), and a still-unpriced sale (net_amount = 0) must not read as
            // "paid" just because there's nothing owed yet.
            $record->payment_status = match (true) {
                $netAmount <= 0 => 'debt',
                $remaining <= 0 => 'paid',
                $received > 0 => 'partial',
                default => 'debt',
            };

            $record->save();

            $row = $this->normalizeSale($record->load(['flock', 'saleItems']));
        } else {
            abort(400, 'Invalid type');
        }

        if (array_key_exists('paid_amount', $data) && $data['paid_amount'] !== null) {
            $this->priceAudit->recordPayment($farmId, $userId, $type, $recordId, $paidBefore, (float) $data['paid_amount']);
        }

        if (array_key_exists('unit_price', $data)) {
            $this->priceAudit->record(
                $farmId,
                $userId,
                $type,
                $recordId,
                $priceBefore,
                $row['unit_price'] !== null ? (float) $row['unit_price'] : null
            );
        }

        return $this->withPriceHistory($farmId, collect([$this->attachReasons($row)]))->first();
    }

    /**
     * لا يُقبل مدفوع أكبر من الإجمالي المسعَّر، لا بدفعة جديدة ولا بخفض السعر (السجل غير المسعَّر بعد يقبل دفعة مسبقة، ويظهر كتناقض حتى يُسعَّر).
     */
    private function assertPaymentWithinTotal(array $data, float $total, float $paid): void
    {
        if ($total > 0 && $paid - $total > 0.005) {
            if (array_key_exists('paid_amount', $data)) {
                abort(422, 'المبلغ المدفوع أكبر من إجمالي السجل — لا يمكن أن تتجاوز الدفعة المبلغ المستحق');
            }
            // خفض السعر تحت ما دُفع فعلاً يجعل السجل «مسدداً» بأكثر من قيمته
            if (array_key_exists('unit_price', $data)) {
                abort(422, 'السعر الجديد يجعل الإجمالي أقل من المبلغ المدفوع ('.round($paid, 2).') — عدّل الدفعة أولاً');
            }
        }
    }

    /**
     * يُلحق بكل صف تاريخ أسعاره (السعر القديم يبقى ظاهراً ولا يُحذف).
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function withPriceHistory(int $farmId, Collection $rows): Collection
    {
        $history = $this->priceAudit->historyFor($farmId, $rows);

        return $rows->map(function (array $row) use ($history): array {
            $row['price_history'] = $history[$row['type'].'-'.$row['record_id']] ?? [];

            return $row;
        });
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    private function fetchRows(int $farmId, string $type, ?int $flockId): Collection
    {
        $rows = collect();

        if ($type === 'all' || $type === 'expense') {
            $q = Expense::with(['flock', 'expenseCategory', 'linkedInventoryTransaction.item.itemType'])->where('farm_id', $farmId);
            if ($flockId) {
                $q->where('flock_id', $flockId);
            }
            $rows = $rows->concat($q->get()->map(fn ($e) => $this->normalizeExpense($e)));
        }

        if ($type === 'all' || $type === 'sale') {
            $q = Sale::with(['flock', 'saleItems'])->where('farm_id', $farmId);
            if ($flockId) {
                $q->where('flock_id', $flockId);
            }
            $rows = $rows->concat($q->get()->map(fn ($s) => $this->normalizeSale($s)));
        }

        if ($type === 'all' || $type === 'water_log') {
            $q = FlockWaterLog::with('flock')->where('farm_id', $farmId);
            if ($flockId) {
                $q->where('flock_id', $flockId);
            }
            $rows = $rows->concat($q->get()->map(fn ($w) => $this->normalizeWaterLog($w)));
        }

        return $rows;
    }

    private function normalizeExpense(Expense $e): array
    {
        $linkedItem = $e->linkedInventoryTransaction?->item
            ?? $this->resolveItemFromDescription($e->farm_id, $e->description);

        return [
            'id' => 'expense-'.$e->id,
            'type' => 'expense',
            'record_id' => $e->id,
            'flock_id' => $e->flock_id,
            'flock_name' => $e->flock?->name,
            'flock_status' => $e->flock?->status,
            'entry_date' => $e->entry_date?->toDateString(),
            'description' => $e->description ?: ($e->expenseCategory?->name ?? $e->description),
            'category_name' => $e->expenseCategory?->name,
            'category_code' => $e->expenseCategory?->code,
            'total_amount' => (float) $e->total_amount,
            'paid_amount' => (float) ($e->paid_amount ?? 0),
            'remaining_amount' => (float) ($e->remaining_amount ?? $e->total_amount),
            'payment_status' => $e->payment_status,
            'unit_price' => $e->unit_price !== null ? (float) $e->unit_price : null,
            'quantity' => $e->quantity !== null ? (float) $e->quantity : null,
            'quantity_unit' => $linkedItem?->input_unit,
            'computed_quantity' => $e->linkedInventoryTransaction?->computed_quantity !== null
                ? (float) $e->linkedInventoryTransaction->computed_quantity
                : null,
            'item_type' => $linkedItem?->itemType?->code,
            'item_id' => $linkedItem?->id,
            'item_name' => $linkedItem?->name,
            'review_reasons' => [],
        ];
    }

    private function normalizeSale(Sale $s): array
    {
        $totalWeight = $s->saleItems->sum('total_weight_kg');
        // 4 منازل: الواجهة تعرض السعر للطن (×1000)، ومنزلتان كانتا تحرّفان سعر الطن (1855 تظهر 1860)
        $avgPrice = $totalWeight > 0 ? round((float) $s->gross_amount / $totalWeight, 4) : null;

        return [
            'id' => 'sale-'.$s->id,
            'type' => 'sale',
            'record_id' => $s->id,
            'flock_id' => $s->flock_id,
            'flock_name' => $s->flock?->name,
            'flock_status' => $s->flock?->status,
            'entry_date' => $s->sale_date?->toDateString(),
            'description' => $s->buyer_name ?? 'بيع',
            'category_name' => 'مبيعات',
            'category_code' => 'sales',
            'total_amount' => (float) $s->net_amount,
            'paid_amount' => (float) ($s->received_amount ?? 0),
            'remaining_amount' => (float) ($s->remaining_amount ?? $s->net_amount),
            'payment_status' => $s->payment_status,
            'unit_price' => $avgPrice,
            'quantity' => $totalWeight > 0 ? (float) $totalWeight : null,
            'quantity_unit' => 'كغ',
            'review_reasons' => [],
        ];
    }

    /**
     * Fallback for old debt expenses that predate linked_inventory_transaction_id:
     * recover the item (and its unit) from the "دين شراء[ تلقائي]: {item}" description text.
     */
    private function resolveItemFromDescription(int $farmId, ?string $description): ?Item
    {
        if (! $description || ! str_starts_with($description, 'دين شراء')) {
            return null;
        }

        $itemName = preg_replace('/^دين شراء(?: تلقائي)?:\s*/u', '', $description);
        $itemName = trim(explode(' — ', $itemName)[0]);

        if ($itemName === '') {
            return null;
        }

        return Item::where('farm_id', $farmId)->where('name', $itemName)->first();
    }

    private function normalizeWaterLog(FlockWaterLog $w): array
    {
        return [
            'id' => 'water_log-'.$w->id,
            'type' => 'water_log',
            'record_id' => $w->id,
            'flock_id' => $w->flock_id,
            'flock_name' => $w->flock?->name,
            'flock_status' => $w->flock?->status,
            'entry_date' => $w->entry_date?->toDateString(),
            'description' => 'استهلاك ماء'.($w->notes ? ' - '.$w->notes : ''),
            'category_name' => 'ماء',
            'category_code' => 'water',
            'total_amount' => (float) ($w->total_amount ?? 0),
            'paid_amount' => (float) ($w->paid_amount ?? 0),
            'remaining_amount' => max(0, (float) (($w->total_amount ?? 0) - ($w->paid_amount ?? 0))),
            'payment_status' => $w->payment_status ?? 'unpaid',
            'unit_price' => ($w->quantity > 0 && $w->total_amount > 0) ? ($w->total_amount / $w->quantity) : null,
            'quantity' => $w->quantity !== null ? (float) $w->quantity : null,
            'quantity_unit' => $w->unit_label,
            'review_reasons' => [],
        ];
    }

    private function attachReasons(array $row): array
    {
        $reasons = [];

        // ── Payment status ────────────────────────────────────────────────────
        if ($row['payment_status'] === null) {
            $reasons[] = 'missing_payment_status';
        } elseif (in_array($row['payment_status'], ['unpaid', 'debt'])) {
            $reasons[] = 'unpaid';
        } elseif ($row['payment_status'] === 'partial') {
            $reasons[] = 'partial_payment';
        }

        // ── Missing price (Business Rule — نهائي) ────────────────────────────
        // Condition 1: expense or water_log with no quantity or no unit_price
        $missingPrice = in_array($row['type'], ['expense', 'water_log'])
            && (
                empty($row['quantity'])        // quantity = 0 or null
                || $row['unit_price'] === null
                || $row['unit_price'] == 0
            );

        // Condition 3: total/net amount <= 0 (both types)
        if (! $missingPrice && $row['total_amount'] <= 0) {
            $missingPrice = true;
        }

        // Always flag missing_price so the user knows they need to enter the cost, even if they marked it as paid
        if ($missingPrice) {
            $reasons[] = 'missing_price';
        }

        // ── Inconsistent financial state ──────────────────────────────────────
        if ($row['paid_amount'] > $row['total_amount'] && $row['total_amount'] > 0) {
            $reasons[] = 'inconsistent_financial_state';
        }

        // Blocking flock closure: only block if price is missing
        $isBlockingClosure = $row['flock_status'] === 'active' && $missingPrice;

        if ($isBlockingClosure) {
            $reasons[] = 'blocking_flock_closure';
        }

        $row['review_reasons'] = $reasons;

        return $row;
    }

    private function buildSummary(Collection $qualifyingRows, bool $detailed = false): array
    {
        $summary = [
            'unpaid_count' => 0,
            'partial_payment_count' => 0,
            'missing_price_count' => 0,
            'missing_payment_status_count' => 0,
            'inconsistent_financial_state_count' => 0,
            'blocking_flock_closure_count' => 0,
        ];

        foreach ($qualifyingRows as $row) {
            foreach ($row['review_reasons'] as $reason) {
                match ($reason) {
                    'unpaid' => $summary['unpaid_count']++,
                    'partial_payment' => $summary['partial_payment_count']++,
                    'missing_price' => $summary['missing_price_count']++,
                    'missing_payment_status' => $summary['missing_payment_status_count']++,
                    'inconsistent_financial_state' => $summary['inconsistent_financial_state_count']++,
                    'blocking_flock_closure' => $summary['blocking_flock_closure_count']++,
                    default => null,
                };
            }
        }

        // Category breakdown for filter cards
        // NOTE: fallback key must not collide with a real ExpenseCategory code (e.g. 'other'
        // is a legitimate category code), otherwise unrelated uncategorized rows merge into it.
        $categoryBreakdown = [];
        foreach ($qualifyingRows as $row) {
            $code = $row['category_code'] ?? 'uncategorized';
            $name = $row['category_name'] ?? 'أخرى';
            if (! isset($categoryBreakdown[$code])) {
                $categoryBreakdown[$code] = [
                    'code' => $code,
                    'name' => $name,
                    'count' => 0,
                    'total_amount' => 0,
                    'remaining_amount' => 0,
                ];
            }
            $categoryBreakdown[$code]['count']++;
            $categoryBreakdown[$code]['total_amount'] += $row['total_amount'];
            $categoryBreakdown[$code]['remaining_amount'] += $row['remaining_amount'];

            // معلومات شاملة للفئة (الكمية الإجمالية، المسعّر وغير المسعّر، متوسط سعر الوحدة)
            $c = &$categoryBreakdown[$code];
            $c['paid_amount'] = ($c['paid_amount'] ?? 0) + $row['paid_amount'];
            $c['quantity'] = ($c['quantity'] ?? 0) + (float) ($row['quantity'] ?? 0);
            $c['quantity_units'] = array_values(array_unique(array_merge($c['quantity_units'] ?? [], array_filter([$row['quantity_unit'] ?? null]))));
            $isPriced = ($row['unit_price'] ?? null) !== null && (float) $row['unit_price'] > 0 && $row['total_amount'] > 0;
            $c['priced_count'] = ($c['priced_count'] ?? 0) + ($isPriced ? 1 : 0);
            $c['unpriced_count'] = ($c['unpriced_count'] ?? 0) + ($isPriced ? 0 : 1);
            $c['priced_quantity'] = ($c['priced_quantity'] ?? 0) + ($isPriced ? (float) ($row['quantity'] ?? 0) : 0);
            $c['priced_amount'] = ($c['priced_amount'] ?? 0) + ($isPriced ? (float) $row['total_amount'] : 0);
            if (($row['item_type'] ?? null) === 'feed' && ($row['computed_quantity'] ?? null) !== null) {
                $c['computed_quantity'] = ($c['computed_quantity'] ?? 0) + (float) $row['computed_quantity'];
            }
            unset($c);
        }

        // Real money owed first, then busiest category, so the cards read most-urgent-first
        usort($categoryBreakdown, fn (array $a, array $b): int => $b['remaining_amount'] <=> $a['remaining_amount']
            ?: $b['count'] <=> $a['count']);

        foreach ($categoryBreakdown as &$c) {
            $units = $c['quantity_units'] ?? [];
            $c['quantity_unit'] = count($units) === 1 ? $units[0] : null; // وحدة واحدة فقط وإلا تُعرض الكمية بلا وحدة
            $c['avg_unit_price'] = ($c['priced_quantity'] ?? 0) > 0
                ? round($c['priced_amount'] / $c['priced_quantity'], 4)
                : null;
            unset($c['quantity_units'], $c['priced_quantity'], $c['priced_amount']);
        }
        unset($c);

        $summary['category_breakdown'] = array_values($categoryBreakdown);

        return $summary;
    }

    private function derivePaymentStatus(float $total, float $paid): string
    {
        if ($paid <= 0) {
            return 'unpaid';
        }
        if ($paid >= $total) {
            return 'paid';
        }

        return 'partial';
    }
}
