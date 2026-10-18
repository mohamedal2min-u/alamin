<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Flock;
use App\Models\FlockFeedLog;
use App\Models\FlockMedicine;
use App\Models\FlockMortality;
use App\Models\FlockWaterLog;
use App\Models\InventoryTransaction;
use App\Models\PriceAuditLog;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * تصحيح الإدخالات اليومية: تعديل أو إلغاء (حذف ناعم مع سبب) على الأفواج النشطة فقط.
 *  - مدير المزرعة: أي سجل في فوج نشط
 *  - المشرف: سجلاته هو خلال 24 ساعة من إدخالها
 *  - المحاسب والشريك: لا يعدّلان الكميات
 * العلف والدواء: تُعكس/تُعدَّل حركة الاستهلاك وكمية المستودع تحت قفل الصف ثم يُعاد تسعير الصنف.
 */
class DailyEntryCorrectionService
{
    /** نافذة تعديل المشرف لسجلاته (بالساعات) */
    public const SUPERVISOR_WINDOW_HOURS = 24;

    private const EPS = 0.000001;

    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly InventoryRepricingService $repricing,
    ) {}

    /** يجد السجل ضمن المزرعة أو يرمي 404 */
    public function find(int $farmId, string $type, int $id): Model
    {
        $class = ActivityLogger::TYPES[$type] ?? null;
        if (! $class) {
            throw new \Exception('نوع السجل غير معروف', 404);
        }

        $entry = $class::with('flock:id,farm_id,status,initial_count')->where('farm_id', $farmId)->find($id);
        if (! $entry) {
            throw new \Exception('السجل غير موجود', 404);
        }

        return $entry;
    }

    /** سبب المنع أو null إن كان مسموحاً: [رسالة, رمز HTTP] */
    public function denial(User $user, bool $isSuperAdmin, Model $entry, ?Flock $flock = null): ?array
    {
        $flock ??= $entry->flock;

        if (! $isSuperAdmin && ! $user->hasRole('farm_admin')) {
            if (! $user->hasRole('supervisor')) {
                return ['ليس لديك صلاحية لتعديل السجلات اليومية', 403];
            }
            $createdBy = $entry->getAttribute('created_by');
            $createdAt = $entry->getAttribute('created_at');
            if ((int) $createdBy !== (int) $user->id) {
                return ['يمكن للمشرف تعديل سجلاته هو فقط', 403];
            }
            if (! $createdAt || $createdAt->lt(now()->subHours(self::SUPERVISOR_WINDOW_HOURS))) {
                return ['انتهت مدة التعديل (24 ساعة من الإدخال). اطلب من مدير المزرعة التصحيح', 403];
            }
        }

        if (! $flock || $flock->status !== 'active') {
            return ['لا يمكن تعديل أو إلغاء سجلات فوج مغلق أو ملغى', 422];
        }

        return null;
    }

    /** أعلام الواجهة لكل سجل */
    public function flags(?User $user, bool $isSuperAdmin, Model $entry, ?Flock $flock = null): array
    {
        $allowed = $user !== null && $this->denial($user, $isSuperAdmin, $entry, $flock) === null;

        return ['can_edit' => $allowed, 'can_void' => $allowed];
    }

    // ─── التعديل ──────────────────────────────────────────────────────────────

    public function update(string $type, Model $entry, array $data, int $userId, ?string $reason = null): Model
    {
        return DB::transaction(function () use ($type, $entry, $data, $userId, $reason): Model {
            $entry = $entry::query()->lockForUpdate()->findOrFail($entry->getKey());
            $this->assertFlockActive($entry);
            $before = $this->logger->snapshot($entry);

            match ($type) {
                'mortality' => $this->updateMortality($entry, $data),
                'feed_log', 'medicine_log' => $this->updateStockEntry($entry, $data, $userId),
                'water_log' => $this->updateWater($entry, $data),
                'note' => $entry->fill(array_intersect_key($data, array_flip(['note_text', 'note_type']))),
                'temperature_log' => $entry->fill(array_intersect_key($data, array_flip(['temperature', 'notes']))),
            };

            if ($entry->isFillable('updated_by')) {
                $entry->updated_by = $userId;
            }
            $entry->save();

            $after = $this->logger->snapshot($entry->refresh());
            $this->logger->log($entry, ActivityLogger::UPDATED, $userId, $before, $after, $reason);

            return $entry;
        });
    }

    private function updateMortality(FlockMortality $entry, array $data): void
    {
        if (array_key_exists('quantity', $data)) {
            $flock = Flock::where('id', $entry->flock_id)->lockForUpdate()->firstOrFail();
            $others = (int) $flock->mortalities()->where('id', '!=', $entry->id)->sum('quantity')
                + (int) $flock->saleItems()->sum('birds_count');
            $newQty = (int) $data['quantity'];

            if ($others + $newQty > (int) $flock->initial_count) {
                $max = max(0, (int) $flock->initial_count - $others);
                throw new \Exception("كمية النفوق ({$newQty}) أكبر من العدد المتاح في الفوج ({$max})", 422);
            }
            $entry->quantity = $newQty;
        }

        $entry->fill(array_intersect_key($data, array_flip(['reason', 'notes'])));
    }

    private function updateWater(FlockWaterLog $entry, array $data): void
    {
        if (array_key_exists('quantity', $data)) {
            $newQty = (float) $data['quantity'];
            $oldQty = (float) $entry->quantity;
            $total = $entry->total_amount !== null ? (float) $entry->total_amount : null;

            // سجل مسعَّر: الإجمالي = سعر الوحدة × الكمية الجديدة، ولا ينزل عن المدفوع
            if ($total !== null && $total > 0 && $oldQty > 0 && abs($newQty - $oldQty) > self::EPS) {
                $newTotal = round(($total / $oldQty) * $newQty, 2);
                $paid = (float) ($entry->paid_amount ?? 0);
                if ($newTotal + 0.004 < $paid) {
                    throw new \Exception(sprintf('الإجمالي الجديد (%s) أقل من المبلغ المدفوع (%s). اطلب من المحاسب تعديل الدفعة أولاً', number_format($newTotal, 2), number_format($paid, 2)), 422);
                }
                $entry->total_amount = $newTotal;
                $entry->payment_status = $paid <= 0 ? 'unpaid' : ($paid + 0.004 >= $newTotal ? 'paid' : 'partial');
            }
            $entry->quantity = $newQty;
        }

        if (array_key_exists('notes', $data)) {
            $entry->notes = $data['notes'];
        }
    }

    /** علف/دواء: تعديل الكمية يعدّل حركة الاستهلاك والمستودع */
    private function updateStockEntry(FlockFeedLog|FlockMedicine $entry, array $data, int $userId): void
    {
        if (array_key_exists('quantity', $data) && abs((float) $data['quantity'] - (float) $entry->quantity) > self::EPS) {
            $this->applyStockChange($entry, (float) $data['quantity'], $userId);
            $entry->quantity = $data['quantity'];
        }

        if (array_key_exists('notes', $data)) {
            $entry->notes = $data['notes'];
        }
    }

    // ─── الإلغاء ──────────────────────────────────────────────────────────────

    public function void(string $type, Model $entry, string $reason, int $userId): Model
    {
        return DB::transaction(function () use ($type, $entry, $reason, $userId): Model {
            $entry = $entry::query()->lockForUpdate()->findOrFail($entry->getKey());
            $this->assertFlockActive($entry);
            $before = $this->logger->snapshot($entry);

            if ($type === 'water_log' && (float) ($entry->paid_amount ?? 0) > 0) {
                throw new \Exception('لا يمكن إلغاء سجل ماء مدفوع. اطلب من المحاسب تصفير الدفعة أولاً', 422);
            }

            if (in_array($type, ['feed_log', 'medicine_log'], true)) {
                $this->applyStockChange($entry, 0.0, $userId, $reason);
            }

            $entry->void_reason = $reason;
            $entry->voided_by = $userId;
            $entry->save();
            $entry->delete();

            $this->logger->log($entry, ActivityLogger::VOIDED, $userId, $before, null, $reason);

            return $entry;
        });
    }

    /** يُعاد التحقق داخل المعاملة: فوج أُغلق للتو لا يُعدَّل */
    private function assertFlockActive(Model $entry): void
    {
        $status = Flock::whereKey($entry->getAttribute('flock_id'))->lockForUpdate()->value('status');
        if ($status !== 'active') {
            throw new \Exception('لا يمكن تعديل أو إلغاء سجلات فوج مغلق أو ملغى', 422);
        }
    }

    // ─── المخزون ──────────────────────────────────────────────────────────────

    /**
     * يغيّر استهلاك السجل إلى $newQty (بوحدة الإدخال، 0 = إلغاء):
     *  - الفرق يعود للمستودع أو يُخصم منه (مع منع النقص عند الزيادة)
     *  - إن كان العلف قد أنشأ شراء نقص تلقائياً يُقلَّص الشراء ودَينه أولاً،
     *    ويُمنع ذلك إن كان المحاسب قد سعّر الدين أو دُفع منه شيء
     *  - ثم يُعاد تسعير الصنف ليبقى متوسط التكلفة وتكلفة الأفواج صحيحين
     */
    private function applyStockChange(FlockFeedLog|FlockMedicine $entry, float $newQty, int $userId, ?string $voidReason = null): void
    {
        if (! $entry->inventory_transaction_id) {
            return; // لا مستودع وقت الإدخال: لا حركة مخزون
        }

        $txn = InventoryTransaction::where('id', $entry->inventory_transaction_id)
            ->where('farm_id', $entry->farm_id)->lockForUpdate()->first();
        if (! $txn) {
            return;
        }

        $wi = WarehouseItem::where('warehouse_id', $txn->warehouse_id)->where('item_id', $txn->item_id)
            ->lockForUpdate()->first();

        $oldOriginal = (float) $txn->original_quantity;
        $oldComputed = (float) $txn->computed_quantity;
        $ratio = $oldOriginal > self::EPS ? $oldComputed / $oldOriginal : (float) ($entry->item?->unit_value ?: 1);
        $newComputed = $newQty * $ratio;
        $delta = $oldComputed - $newComputed; // موجب = يعود للمستودع

        if ($delta < -self::EPS) {
            $available = (float) ($wi?->current_quantity ?? 0);
            if ($available + self::EPS < -$delta) {
                throw new \Exception(sprintf(
                    'الكمية الإضافية أكبر من المتوفر بالمخزون. المتوفر: %s %s',
                    rtrim(rtrim(number_format($available / ($ratio ?: 1), 3), '0'), '.'),
                    $txn->input_unit ?? ''
                ), 422);
            }
        }

        $purchaseReduce = 0.0;
        if ($delta > self::EPS && $entry instanceof FlockFeedLog) {
            $purchaseReduce = $this->shrinkAutoPurchase($entry, $txn, $delta);
        }

        if ($wi) {
            $wi->current_quantity = max(0, (float) $wi->current_quantity + $delta - $purchaseReduce);
            $wi->save();
        }

        $unitPrice = $txn->unit_price !== null ? (float) $txn->unit_price : null;
        $txn->original_quantity = $newQty;
        $txn->computed_quantity = $newComputed;
        $txn->total_amount = ($unitPrice && $newComputed > 0) ? round($unitPrice * $newComputed, 2) : null;
        if ($voidReason !== null) {
            $txn->notes = trim('ملغى: '.$voidReason.($txn->notes ? ' | '.$txn->notes : ''));
        }
        $txn->updated_by = $userId;
        $txn->save();

        $this->repricing->repriceItem((int) $txn->farm_id, (int) $txn->item_id);
    }

    /** يُقلّص شراء النقص التلقائي بمقدار ما عاد من الكمية؛ يُرجع ما خُصم منه (بوحدة المحتوى) */
    private function shrinkAutoPurchase(FlockFeedLog $entry, InventoryTransaction $consumption, float $returned): float
    {
        $purchase = $this->autoPurchaseOf($entry, $consumption);
        if (! $purchase) {
            return 0.0;
        }

        $pComputed = (float) $purchase->computed_quantity;
        $reduce = min($pComputed, $returned);
        if ($reduce <= self::EPS) {
            return 0.0;
        }

        $expense = Expense::where('farm_id', $entry->farm_id)
            ->where('linked_inventory_transaction_id', $purchase->id)->lockForUpdate()->first();

        if ($expense) {
            $pricedByAccountant = PriceAuditLog::where('farm_id', $entry->farm_id)
                ->where('record_type', 'expense')->where('record_id', $expense->id)->exists();
            if ((float) ($expense->paid_amount ?? 0) > 0 || $pricedByAccountant) {
                throw new \Exception('هذا السجل أنشأ دين شراء علف تلقائياً (لنقص المخزون) وقد سعّره المحاسب أو دُفع منه. اطلب من المحاسب مراجعة الدين قبل التعديل أو الإلغاء', 422);
            }
        }

        $pOriginal = (float) $purchase->original_quantity;
        $pRatio = $pOriginal > self::EPS ? $pComputed / $pOriginal : 1.0;
        $newComputed = $pComputed - $reduce;

        if ($newComputed <= self::EPS) {
            // لم يبقَ من الشراء شيء: يُحذف الشراء التلقائي ودَينه
            $expense?->delete();
            $entry->auto_purchase_transaction_id = null;
            $purchase->delete();

            return $reduce;
        }

        $newOriginal = $newComputed / $pRatio;
        $purchase->computed_quantity = $newComputed;
        $purchase->original_quantity = $newOriginal;
        if ($purchase->unit_price !== null && (float) $purchase->unit_price > 0) {
            $purchase->total_amount = round((float) $purchase->unit_price * $newOriginal, 2);
        }
        $purchase->save();

        if ($expense) {
            $total = $expense->unit_price !== null ? round((float) $expense->unit_price * $newOriginal, 2) : (float) $expense->total_amount;
            $expense->quantity = $newOriginal;
            $expense->total_amount = $total;
            $expense->remaining_amount = max(0, $total - (float) ($expense->paid_amount ?? 0));
            $expense->save();
        }
        $entry->auto_purchase_transaction_id = $purchase->id;

        return $reduce;
    }

    /** شراء النقص التلقائي المرتبط (عمود صريح، أو للسجلات القديمة: الشراء المُنشأ قبيل الاستهلاك نفسه) */
    private function autoPurchaseOf(FlockFeedLog $entry, InventoryTransaction $consumption): ?InventoryTransaction
    {
        if ($entry->auto_purchase_transaction_id) {
            return InventoryTransaction::where('id', $entry->auto_purchase_transaction_id)
                ->where('farm_id', $entry->farm_id)->lockForUpdate()->first();
        }

        if (! $consumption->created_at) {
            return null;
        }

        $claimed = FlockFeedLog::withTrashed()->whereNotNull('auto_purchase_transaction_id')
            ->where('farm_id', $entry->farm_id)->pluck('auto_purchase_transaction_id');

        return InventoryTransaction::where('farm_id', $entry->farm_id)
            ->where('item_id', $consumption->item_id)
            ->where('warehouse_id', $consumption->warehouse_id)
            ->where('source_module', 'auto_purchase')
            ->where('id', '<', $consumption->id)
            ->where('created_by', $consumption->created_by)
            ->whereBetween('created_at', [$consumption->created_at->copy()->subSeconds(5), $consumption->created_at])
            ->whereNotIn('id', $claimed)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
    }
}
