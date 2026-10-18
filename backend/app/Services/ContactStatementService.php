<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Expense;
use App\Models\FlockWaterLog;
use App\Models\InventoryTransaction;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * أرصدة الموردين والتجار وكشوف حساباتهم.
 * الموردون (مصاريف + ماء): ما علينا لهم. التجار (مبيعات): ما لنا عليهم.
 */
class ContactStatementService
{
    /**
     * قائمة الجهات مع الرصيد وأعمار الديون (0-30، 31-60، أكثر من 60 يوماً).
     */
    public function balances(int $farmId, array $filters = []): array
    {
        $contacts = Contact::where('farm_id', $farmId)
            ->when(in_array($filters['type'] ?? null, ['supplier', 'buyer'], true), fn ($q) => $q->whereIn('type', [$filters['type'], 'both']))
            ->when(! empty($filters['active']), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        $payable = $this->mergeSides(
            $this->aggregate('expenses', 'entry_date', 'total_amount', 'paid_amount', $farmId),
            $this->aggregate('flock_water_logs', 'entry_date', 'total_amount', 'paid_amount', $farmId),
        );
        $receivable = $this->aggregate('sales', 'sale_date', 'net_amount', 'received_amount', $farmId);

        $rows = $contacts->map(fn (Contact $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'type' => $c->type,
            'phone' => $c->phone,
            'whatsapp' => $c->whatsapp,
            'notes' => $c->notes,
            'is_active' => (bool) $c->is_active,
            'payable' => $payable[$c->id] ?? $this->emptySide(),
            'receivable' => $receivable[$c->id] ?? $this->emptySide(),
        ])->values();

        $sum = fn (string $side, string $key) => round($rows->sum(fn ($r) => $r[$side][$key]), 2);

        return [
            'data' => $rows->all(),
            'summary' => [
                'payable_balance' => $sum('payable', 'balance'),
                'receivable_balance' => $sum('receivable', 'balance'),
                'payable_aging' => [
                    'd0_30' => round($rows->sum(fn ($r) => $r['payable']['aging']['d0_30']), 2),
                    'd31_60' => round($rows->sum(fn ($r) => $r['payable']['aging']['d31_60']), 2),
                    'd60_plus' => round($rows->sum(fn ($r) => $r['payable']['aging']['d60_plus']), 2),
                ],
                'receivable_aging' => [
                    'd0_30' => round($rows->sum(fn ($r) => $r['receivable']['aging']['d0_30']), 2),
                    'd31_60' => round($rows->sum(fn ($r) => $r['receivable']['aging']['d31_60']), 2),
                    'd60_plus' => round($rows->sum(fn ($r) => $r['receivable']['aging']['d60_plus']), 2),
                ],
            ],
        ];
    }

    /**
     * كشف حساب جهة: الفواتير والدفعات مرتبة بالتاريخ مع رصيد جارٍ.
     */
    public function statement(Contact $contact, ?string $from = null, ?string $to = null): array
    {
        $farmId = (int) $contact->farm_id;
        $entries = collect();

        $expenses = Expense::with(['flock:id,name', 'expenseCategory:id,name'])
            ->where('farm_id', $farmId)->where('contact_id', $contact->id)->get();
        foreach ($expenses as $e) {
            $entries->push($this->invoice('expense', $e->id, $e->entry_date, 'payable',
                $e->description ?: ($e->expenseCategory?->name ?? 'مصروف'), $e->flock?->name, (float) $e->total_amount));
        }

        $waterLogs = FlockWaterLog::with('flock:id,name')
            ->where('farm_id', $farmId)->where('contact_id', $contact->id)->get();
        foreach ($waterLogs as $w) {
            $qty = $w->quantity !== null ? ' '.rtrim(rtrim(number_format((float) $w->quantity, 3, '.', ''), '0'), '.').' '.($w->unit_label ?? '') : '';
            $entries->push($this->invoice('water_log', $w->id, $w->entry_date, 'payable',
                trim('ماء'.$qty), $w->flock?->name, (float) ($w->total_amount ?? 0)));
        }

        $sales = Sale::with(['flock:id,name'])->withSum('saleItems', 'total_weight_kg')
            ->where('farm_id', $farmId)->where('contact_id', $contact->id)->get();
        foreach ($sales as $s) {
            $kg = (float) ($s->sale_items_sum_total_weight_kg ?? 0);
            $entries->push($this->invoice('sale', $s->id, $s->sale_date, 'receivable',
                'بيع'.($kg > 0 ? ' '.round($kg, 1).' كغ' : ''), $s->flock?->name, (float) $s->net_amount));
        }

        // حمولات مدفوعة كاملة عند الاستلام (بلا سجل دين): فاتورة ودفعتها في اليوم نفسه
        $paidShipments = InventoryTransaction::with(['item:id,name', 'flock:id,name'])
            ->where('farm_id', $farmId)->where('contact_id', $contact->id)
            ->where('transaction_type', 'purchase')
            ->whereDoesntHave('linkedExpenses')
            ->get();
        foreach ($paidShipments as $t) {
            $amount = (float) ($t->total_amount ?? 0);
            $entries->push($this->invoice('shipment', $t->id, $t->transaction_date, 'payable',
                'حمولة: '.($t->item?->name ?? ''), $t->flock?->name, $amount));
            if ((float) $t->paid_amount > 0) {
                $entries->push($this->paymentEntry('shipment', $t->id, $t->transaction_date, 'payable',
                    (float) $t->paid_amount, null, 'مدفوعة عند الاستلام', $t->id));
            }
        }

        $payments = Payment::where('farm_id', $farmId)
            ->where(function ($q) use ($expenses, $waterLogs, $sales): void {
                $q->whereRaw('1 = 0');
                foreach (['expense' => $expenses, 'water_log' => $waterLogs, 'sale' => $sales] as $type => $records) {
                    if ($records->isNotEmpty()) {
                        $q->orWhere(fn ($qq) => $qq->where('payable_type', $type)->whereIn('payable_id', $records->modelKeys()));
                    }
                }
            })
            ->get();
        foreach ($payments as $p) {
            $side = $p->payable_type === 'sale' ? 'receivable' : 'payable';
            $entries->push($this->paymentEntry($p->payable_type, $p->payable_id, $p->paid_at, $side,
                (float) $p->amount, $p->method, $p->note, $p->id, (bool) $p->is_opening));
        }

        // ترتيب: التاريخ، ثم الفاتورة قبل دفعتها في اليوم نفسه
        $entries = $entries->sort(fn ($a, $b) => [$a['date'], $a['kind'] === 'invoice' ? 0 : 1, $a['sort_id']]
            <=> [$b['date'], $b['kind'] === 'invoice' ? 0 : 1, $b['sort_id']])->values();

        $opening = ['payable' => 0.0, 'receivable' => 0.0];
        $running = ['payable' => 0.0, 'receivable' => 0.0];
        $totals = [
            'payable' => ['invoiced' => 0.0, 'paid' => 0.0],
            'receivable' => ['invoiced' => 0.0, 'paid' => 0.0],
        ];
        $out = [];

        foreach ($entries as $entry) {
            $delta = $entry['kind'] === 'invoice' ? $entry['amount'] : -$entry['amount'];
            $running[$entry['side']] = round($running[$entry['side']] + $delta, 2);

            if ($from && $entry['date'] < $from) {
                $opening[$entry['side']] = $running[$entry['side']];

                continue;
            }
            if ($to && $entry['date'] > $to) {
                continue;
            }

            $totals[$entry['side']][$entry['kind'] === 'invoice' ? 'invoiced' : 'paid'] += $entry['amount'];
            unset($entry['sort_id']);
            $entry['running_payable'] = $running['payable'];
            $entry['running_receivable'] = $running['receivable'];
            // موجب = لنا عليه، سالب = علينا له
            $entry['running_net'] = round($running['receivable'] - $running['payable'], 2);
            $out[] = $entry;
        }

        $closing = [
            'payable' => $out ? end($out)['running_payable'] : $opening['payable'],
            'receivable' => $out ? end($out)['running_receivable'] : $opening['receivable'],
        ];

        return [
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'type' => $contact->type,
                'phone' => $contact->phone,
                'whatsapp' => $contact->whatsapp,
                'is_active' => (bool) $contact->is_active,
            ],
            'from' => $from,
            'to' => $to,
            'opening' => $opening,
            'closing' => $closing + ['net' => round($closing['receivable'] - $closing['payable'], 2)],
            'totals' => array_map(fn ($t) => array_map(fn ($v) => round($v, 2), $t), $totals),
            'entries' => $out,
        ];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function invoice(string $type, int $id, $date, string $side, string $description, ?string $flock, float $amount): array
    {
        return [
            'kind' => 'invoice',
            'side' => $side,
            'record_type' => $type,
            'record_id' => $id,
            'date' => $this->dateString($date),
            'description' => $description,
            'flock_name' => $flock,
            'amount' => round($amount, 2),
            'unpriced' => $amount <= 0,
            'method' => null,
            'note' => null,
            'is_opening' => false,
            'sort_id' => $id,
        ];
    }

    private function paymentEntry(string $type, int $recordId, $date, string $side, float $amount, ?string $method, ?string $note, int $sortId, bool $opening = false): array
    {
        return [
            'kind' => 'payment',
            'side' => $side,
            'record_type' => $type,
            'record_id' => $recordId,
            'date' => $this->dateString($date),
            'description' => $side === 'receivable' ? 'قبض' : 'دفع',
            'flock_name' => null,
            'amount' => round($amount, 2),
            'unpriced' => false,
            'method' => $method,
            'note' => $note,
            'is_opening' => $opening,
            'sort_id' => $sortId,
        ];
    }

    private function dateString($date): string
    {
        return $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : substr((string) $date, 0, 10);
    }

    private function emptySide(): array
    {
        return ['total' => 0.0, 'paid' => 0.0, 'balance' => 0.0, 'open_count' => 0,
            'aging' => ['d0_30' => 0.0, 'd31_60' => 0.0, 'd60_plus' => 0.0]];
    }

    /**
     * مجاميع لكل جهة من جدول واحد، مع أعمار المتبقي حسب تاريخ السجل.
     *
     * @return array<int, array<string, mixed>>
     */
    private function aggregate(string $table, string $dateCol, string $totalCol, string $paidCol, int $farmId): array
    {
        $d30 = now()->subDays(30)->toDateString();
        $d60 = now()->subDays(60)->toDateString();
        $total = "COALESCE({$totalCol}, 0)";
        $paid = "COALESCE({$paidCol}, 0)";
        $rem = "(CASE WHEN {$total} - {$paid} > 0 THEN {$total} - {$paid} ELSE 0 END)";

        $rows = DB::table($table)
            ->where('farm_id', $farmId)
            ->whereNotNull('contact_id')
            ->groupBy('contact_id')
            ->selectRaw(
                "contact_id, SUM({$total}) AS total, SUM({$paid}) AS paid,
                 SUM(CASE WHEN {$rem} > 0 THEN 1 ELSE 0 END) AS open_count,
                 SUM(CASE WHEN {$dateCol} >= ? THEN {$rem} ELSE 0 END) AS d0_30,
                 SUM(CASE WHEN {$dateCol} < ? AND {$dateCol} >= ? THEN {$rem} ELSE 0 END) AS d31_60,
                 SUM(CASE WHEN {$dateCol} < ? THEN {$rem} ELSE 0 END) AS d60_plus",
                [$d30, $d30, $d60, $d60]
            )
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->contact_id] = [
                'total' => round((float) $r->total, 2),
                'paid' => round((float) $r->paid, 2),
                // الرصيد = الإجمالي − المدفوع (سالب = دفعة مقدمة)
                'balance' => round((float) $r->total - (float) $r->paid, 2),
                'open_count' => (int) $r->open_count,
                'aging' => [
                    'd0_30' => round((float) $r->d0_30, 2),
                    'd31_60' => round((float) $r->d31_60, 2),
                    'd60_plus' => round((float) $r->d60_plus, 2),
                ],
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $a
     * @param  array<int, array<string, mixed>>  $b
     * @return array<int, array<string, mixed>>
     */
    private function mergeSides(array $a, array $b): array
    {
        foreach ($b as $id => $side) {
            if (! isset($a[$id])) {
                $a[$id] = $side;

                continue;
            }
            foreach (['total', 'paid', 'balance'] as $k) {
                $a[$id][$k] = round($a[$id][$k] + $side[$k], 2);
            }
            $a[$id]['open_count'] += $side['open_count'];
            foreach ($side['aging'] as $k => $v) {
                $a[$id]['aging'][$k] = round($a[$id]['aging'][$k] + $v, 2);
            }
        }

        return $a;
    }
}
