<?php

namespace App\Services;

use App\Models\PriceAuditLog;
use Illuminate\Support\Collection;

class PriceAuditService
{
    /**
     * يسجّل تغيّر السعر. old = null يعني أول تسعير. لا يسجّل شيئاً إن لم يتغيّر السعر.
     */
    public function record(int $farmId, ?int $userId, string $type, int $recordId, ?float $old, ?float $new): void
    {
        if ($old !== null && $new !== null && abs($old - $new) < 0.00005) {
            return;
        }

        if ($old === null && $new === null) {
            return;
        }

        PriceAuditLog::create([
            'farm_id' => $farmId,
            'user_id' => $userId,
            'record_type' => $type,
            'record_id' => $recordId,
            'old_price' => $old,
            'new_price' => $new,
        ]);
    }

    /**
     * يُرجع تاريخ الأسعار لكل سجل، مفهرساً بـ "type-id".
     * كل عنصر: old_price (يُعرض مشطوباً إن لم يكن null) و new_price ومن عدّل ومتى.
     *
     * @param  Collection<int, array{type:string, record_id:int}>  $rows
     * @return array<string, list<array<string, mixed>>>
     */
    public function historyFor(int $farmId, Collection $rows): array
    {
        $history = [];

        foreach ($rows->groupBy('type') as $type => $group) {
            $logs = PriceAuditLog::with('user:id,name')
                ->where('farm_id', $farmId)
                ->where('record_type', $type)
                ->whereIn('record_id', $group->pluck('record_id')->all())
                ->orderBy('id')
                ->get();

            foreach ($logs as $log) {
                $history["{$type}-{$log->record_id}"][] = [
                    'old_price' => $log->old_price,
                    'new_price' => $log->new_price,
                    'by' => $log->user?->name,
                    'at' => $log->created_at?->toIso8601String(),
                ];
            }
        }

        return $history;
    }
}
