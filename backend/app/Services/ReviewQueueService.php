<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Sale;

class ReviewQueueService
{
    /**
     * @param  array{type?:string, reason?:string, flock_id?:int|string, page?:int, per_page?:int}  $filters
     */
    public function getQueue(int $farmId, array $filters = []): array
    {
        $type    = $filters['type']     ?? 'all';
        $reason  = $filters['reason']   ?? null;
        $flockId = isset($filters['flock_id']) && $filters['flock_id'] !== '' ? (int) $filters['flock_id'] : null;
        $page    = max(1, (int) ($filters['page']    ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));

        $rows = $this->fetchRows($farmId, $type, $flockId);

        // Attach reasons then keep only qualifying rows
        $rows = $rows->map(fn ($row) => $this->attachReasons($row));
        $rows = $rows->filter(fn ($row) => count($row['review_reasons']) > 0);

        // Filter by specific reason if requested
        if ($reason) {
            $rows = $rows->filter(fn ($row) => in_array($reason, $row['review_reasons']));
        }

        $rows = $rows->values();

        $summary = $this->buildSummary($rows);

        // Manual pagination after reason-filtering so summary always reflects full filtered set
        $total     = $rows->count();
        $offset    = ($page - 1) * $perPage;
        $pageItems = $rows->slice($offset, $perPage)->values();

        return [
            'summary' => $summary,
            'data'    => $pageItems->toArray(),
            'meta'    => [
                'total'        => $total,
                'current_page' => $page,
                'per_page'     => $perPage,
            ],
        ];
    }

    /**
     * Update a single record, recalculate financial fields, return fresh row with reasons.
     */
    public function updateRecord(int $farmId, string $type, int $recordId, array $data): array
    {
        if ($type === 'expense') {
            $record = Expense::where('farm_id', $farmId)->findOrFail($recordId);

            if (array_key_exists('unit_price', $data)) {
                $record->unit_price = $data['unit_price'];
                if ($record->quantity !== null && $data['unit_price'] !== null) {
                    $record->total_amount = $record->quantity * $data['unit_price'];
                }
            }

            if (array_key_exists('paid_amount', $data)) {
                $record->paid_amount = $data['paid_amount'];
            }

            $record->remaining_amount = max(0, $record->total_amount - $record->paid_amount);
            $record->payment_status   = $this->derivePaymentStatus(
                (float) $record->total_amount,
                (float) $record->paid_amount
            );

            $record->save();

            $row = $this->normalizeExpense($record->load(['flock', 'expenseCategory']));

        } else {
            $record = Sale::where('farm_id', $farmId)->findOrFail($recordId);

            if (array_key_exists('paid_amount', $data)) {
                $record->received_amount = $data['paid_amount'];
            }

            $record->remaining_amount = max(0, $record->net_amount - $record->received_amount);
            $record->payment_status   = $this->derivePaymentStatus(
                (float) $record->net_amount,
                (float) $record->received_amount
            );

            $record->save();

            $row = $this->normalizeSale($record->load('flock'));
        }

        return $this->attachReasons($row);
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    private function fetchRows(int $farmId, string $type, ?int $flockId): \Illuminate\Support\Collection
    {
        $rows = collect();

        if ($type === 'all' || $type === 'expense') {
            $q = Expense::with(['flock', 'expenseCategory'])->where('farm_id', $farmId);
            if ($flockId) {
                $q->where('flock_id', $flockId);
            }
            $rows = $rows->concat($q->get()->map(fn ($e) => $this->normalizeExpense($e)));
        }

        if ($type === 'all' || $type === 'sale') {
            $q = Sale::with('flock')->where('farm_id', $farmId);
            if ($flockId) {
                $q->where('flock_id', $flockId);
            }
            $rows = $rows->concat($q->get()->map(fn ($s) => $this->normalizeSale($s)));
        }

        return $rows;
    }

    private function normalizeExpense(Expense $e): array
    {
        return [
            'id'               => 'expense-' . $e->id,
            'type'             => 'expense',
            'record_id'        => $e->id,
            'flock_id'         => $e->flock_id,
            'flock_name'       => $e->flock?->name,
            'flock_status'     => $e->flock?->status,
            'entry_date'       => $e->entry_date?->toDateString(),
            'description'      => $e->expenseCategory?->name ?? $e->description,
            'total_amount'     => (float) $e->total_amount,
            'paid_amount'      => (float) ($e->paid_amount ?? 0),
            'remaining_amount' => (float) ($e->remaining_amount ?? $e->total_amount),
            'payment_status'   => $e->payment_status,
            'unit_price'       => $e->unit_price !== null ? (float) $e->unit_price : null,
            'quantity'         => $e->quantity !== null ? (float) $e->quantity : null,
            'review_reasons'   => [],
        ];
    }

    private function normalizeSale(Sale $s): array
    {
        return [
            'id'               => 'sale-' . $s->id,
            'type'             => 'sale',
            'record_id'        => $s->id,
            'flock_id'         => $s->flock_id,
            'flock_name'       => $s->flock?->name,
            'flock_status'     => $s->flock?->status,
            'entry_date'       => $s->sale_date?->toDateString(),
            'description'      => $s->buyer_name ?? 'بيع',
            'total_amount'     => (float) $s->net_amount,
            'paid_amount'      => (float) ($s->received_amount ?? 0),
            'remaining_amount' => (float) ($s->remaining_amount ?? $s->net_amount),
            'payment_status'   => $s->payment_status,
            'unit_price'       => null,
            'quantity'         => null,
            'review_reasons'   => [],
        ];
    }

    private function attachReasons(array $row): array
    {
        $reasons = [];

        // ── Payment status ────────────────────────────────────────────────────
        if ($row['payment_status'] === null) {
            $reasons[] = 'missing_payment_status';
        } elseif ($row['payment_status'] === 'unpaid') {
            $reasons[] = 'unpaid';
        } elseif ($row['payment_status'] === 'partial') {
            $reasons[] = 'partial_payment';
        }

        // ── Missing price (Business Rule — نهائي) ────────────────────────────
        // Conditions 1+2: expense with no quantity or no unit_price
        $missingPrice = $row['type'] === 'expense'
            && (
                empty($row['quantity'])        // quantity = 0 or null
                || $row['unit_price'] === null
                || $row['unit_price'] == 0
            );

        // Condition 3: total/net amount <= 0 (both types)
        if (! $missingPrice && $row['total_amount'] <= 0) {
            $missingPrice = true;
        }

        if ($missingPrice) {
            $reasons[] = 'missing_price';
        }

        // ── Inconsistent financial state ──────────────────────────────────────
        if ($row['paid_amount'] > $row['total_amount'] && $row['total_amount'] > 0) {
            $reasons[] = 'inconsistent_financial_state';
        }

        // ── Blocking flock closure ────────────────────────────────────────────
        // Active flock + (unpaid/partial OR missing_price)
        $isBlockingClosure = $row['flock_status'] === 'active'
            && (
                in_array($row['payment_status'], ['unpaid', 'partial'])
                || $missingPrice
            );

        if ($isBlockingClosure) {
            $reasons[] = 'blocking_flock_closure';
        }

        $row['review_reasons'] = $reasons;

        return $row;
    }

    private function buildSummary(\Illuminate\Support\Collection $qualifyingRows): array
    {
        $summary = [
            'unpaid_count'                       => 0,
            'partial_payment_count'              => 0,
            'missing_price_count'                => 0,
            'missing_payment_status_count'       => 0,
            'inconsistent_financial_state_count' => 0,
            'blocking_flock_closure_count'       => 0,
        ];

        foreach ($qualifyingRows as $row) {
            foreach ($row['review_reasons'] as $reason) {
                match ($reason) {
                    'unpaid'                       => $summary['unpaid_count']++,
                    'partial_payment'              => $summary['partial_payment_count']++,
                    'missing_price'                => $summary['missing_price_count']++,
                    'missing_payment_status'       => $summary['missing_payment_status_count']++,
                    'inconsistent_financial_state' => $summary['inconsistent_financial_state_count']++,
                    'blocking_flock_closure'       => $summary['blocking_flock_closure_count']++,
                    default                        => null,
                };
            }
        }

        return $summary;
    }

    private function derivePaymentStatus(float $total, float $paid): string
    {
        if ($paid <= 0) return 'unpaid';
        if ($paid >= $total) return 'paid';
        return 'partial';
    }
}
