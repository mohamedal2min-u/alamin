<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\FlockWaterLog;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * سجل الدفعات المؤرَّخ. القاعدة: مجموع دفعات السجل = المدفوع التراكمي عليه
 * (paid_amount للمصروف والماء، received_amount للبيع). الحساب نفسه (المتبقي وحالة الدفع
 * ومنع تجاوز الإجمالي) يبقى في ReviewQueueService كي لا يختلف بين المسارين.
 */
class PaymentLedgerService
{
    /** @var array<string, class-string<Model>> */
    public const TYPES = [
        'expense' => Expense::class,
        'sale' => Sale::class,
        'water_log' => FlockWaterLog::class,
    ];

    public static function typeOf(Model $record): string
    {
        return match (true) {
            $record instanceof Sale => 'sale',
            $record instanceof FlockWaterLog => 'water_log',
            $record instanceof Expense => 'expense',
            default => throw new \InvalidArgumentException('Unsupported payable'),
        };
    }

    public static function paidOf(Model $record): float
    {
        return (float) ($record instanceof Sale ? $record->received_amount : $record->paid_amount) ?: 0.0;
    }

    public static function totalOf(Model $record): float
    {
        return (float) ($record instanceof Sale ? $record->net_amount : $record->total_amount) ?: 0.0;
    }

    public static function dateOf(Model $record): string
    {
        $date = $record instanceof Sale ? $record->sale_date : $record->entry_date;

        return $date ? \Illuminate\Support\Carbon::parse($date)->toDateString() : now()->toDateString();
    }

    /**
     * يضيف صف دفعة بالفرق بين المدفوع على السجل ومجموع دفعاته (إن وُجد فرق).
     */
    public function sync(Model $record, string $paidAt, string $method, ?string $note, ?int $userId, bool $opening = false): ?Payment
    {
        $type = self::typeOf($record);
        $ledgerSum = (float) Payment::where('farm_id', $record->farm_id)
            ->where('payable_type', $type)
            ->where('payable_id', $record->id)
            ->sum('amount');

        $diff = round(self::paidOf($record) - $ledgerSum, 2);
        if (abs($diff) < 0.005) {
            return null;
        }

        return Payment::create([
            'farm_id' => $record->farm_id,
            'contact_id' => $record->contact_id ?? null,
            'payable_type' => $type,
            'payable_id' => $record->id,
            'amount' => $diff,
            'paid_at' => $paidAt,
            'method' => in_array($method, Payment::METHODS, true) ? $method : 'other',
            'note' => $note,
            'is_opening' => $opening,
            'created_by' => $userId,
        ]);
    }

    /**
     * تسجيل دفعة جديدة (للمحاسب): تُضاف إلى المدفوع وتُعاد حسابات السجل عبر ReviewQueueService.
     *
     * @param  array{amount:float|string, paid_at:string, method:string, note?:?string}  $data
     */
    public function record(int $farmId, string $type, int $recordId, array $data, int $userId): array
    {
        $class = self::TYPES[$type] ?? abort(422, 'النوع غير صالح');

        return DB::transaction(function () use ($class, $farmId, $type, $recordId, $data, $userId): array {
            $record = $class::where('farm_id', $farmId)->lockForUpdate()->findOrFail($recordId);
            $record->loadMissing('flock:id,status');

            // فوج مغلق: ربحه وُزِّع على الشركاء، فلا تُسجَّل عليه دفعات
            if (in_array($record->flock?->status, ['closed', 'cancelled'], true)) {
                abort(403, 'الفوج مغلق: لا يمكن التعديل عليه');
            }

            $amount = round((float) $data['amount'], 2);
            $total = self::totalOf($record);
            $paid = self::paidOf($record);

            if ($total > 0 && $paid + $amount - $total > 0.005) {
                abort(422, 'الدفعة ('.$amount.') أكبر من المتبقي على السجل ('.round(max(0, $total - $paid), 2).')');
            }

            return app(ReviewQueueService::class)->updateRecord(
                $farmId,
                $type,
                $recordId,
                ['paid_amount' => round($paid + $amount, 2)],
                $userId,
                [
                    'paid_at' => $data['paid_at'],
                    'method' => $data['method'],
                    'note' => $data['note'] ?? null,
                ]
            );
        });
    }

    /**
     * دفعات مجموعة سجلات دفعة واحدة، مفتاحها "type-id".
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, list<array<string, mixed>>>
     */
    public function historyFor(int $farmId, Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $query = Payment::with('createdByUser:id,name')->where('farm_id', $farmId)
            ->where(function ($q) use ($rows): void {
                foreach ($rows->groupBy('type') as $type => $group) {
                    $q->orWhere(fn ($qq) => $qq->where('payable_type', $type)->whereIn('payable_id', $group->pluck('record_id')));
                }
            })
            ->orderBy('paid_at')->orderBy('id');

        $out = [];
        foreach ($query->get() as $p) {
            $out[$p->payable_type.'-'.$p->payable_id][] = self::present($p);
        }

        return $out;
    }

    public static function present(Payment $p): array
    {
        return [
            'id' => $p->id,
            'amount' => (float) $p->amount,
            'paid_at' => $p->paid_at?->toDateString(),
            'method' => $p->method,
            'note' => $p->note,
            'is_opening' => (bool) $p->is_opening,
            'created_by_name' => $p->createdByUser?->name,
        ];
    }

    /**
     * نقل المدفوع التراكمي القديم: دفعة افتتاحية واحدة لكل سجل مدفوع ليس له أي دفعة بعد،
     * بتاريخ السجل. إعادة التشغيل لا تضيف شيئاً (idempotent).
     */
    public function backfillOpening(): int
    {
        $note = 'رصيد افتتاحي: مدفوع مسجّل قبل سجل الدفعات';
        $inserted = 0;

        foreach ([
            ['expenses', 'expense', 'paid_amount', 'entry_date'],
            ['sales', 'sale', 'received_amount', 'sale_date'],
            ['flock_water_logs', 'water_log', 'paid_amount', 'entry_date'],
        ] as [$table, $type, $paidCol, $dateCol]) {
            $select = DB::table($table.' as r')
                ->selectRaw(
                    "r.farm_id, r.contact_id, ?, r.id, r.{$paidCol}, r.{$dateCol}, 'other', ?, TRUE, r.created_by, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP",
                    [$type, $note]
                )
                ->where("r.{$paidCol}", '>', 0)
                ->whereNotExists(function ($q) use ($type): void {
                    $q->select(DB::raw(1))->from('payments as p')
                        ->where('p.payable_type', $type)
                        ->whereColumn('p.payable_id', 'r.id');
                });

            $inserted += DB::table('payments')->insertUsing(
                ['farm_id', 'contact_id', 'payable_type', 'payable_id', 'amount', 'paid_at', 'method', 'note', 'is_opening', 'created_by', 'created_at', 'updated_at'],
                $select
            );
        }

        return $inserted;
    }
}
