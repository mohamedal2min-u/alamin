<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\FlockWaterLog;
use App\Models\PriceAuditLog;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PriceAuditController extends Controller
{
    /**
     * GET /accounting/price-audit
     * سجل كل تعديلات الأسعار (الأحدث أولاً). التعديل = سطر old_price فيه قيمة.
     * Query: edits_only=1 لإظهار التعديلات فقط دون أول تسعير، record_type، page، per_page.
     */
    public function index(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $perPage = min(100, max(1, (int) $request->query('per_page', 30)));

        $query = PriceAuditLog::with('user:id,name')
            ->where('farm_id', $farmId)
            ->orderByDesc('id');

        if (in_array($request->query('record_type'), ['expense', 'water_log', 'sale'], true)) {
            $query->where('record_type', $request->query('record_type'));
        }

        if ($request->boolean('edits_only')) {
            $query->whereNotNull('old_price');
        }

        $page = $query->paginate($perPage);

        $labels = $this->describeRecords($farmId, collect($page->items()));

        return response()->json([
            'data' => collect($page->items())->map(fn (PriceAuditLog $log) => [
                'id' => $log->id,
                'record_type' => $log->record_type,
                'record_id' => $log->record_id,
                'description' => $labels["{$log->record_type}-{$log->record_id}"] ?? null,
                'old_price' => $log->old_price,
                'new_price' => $log->new_price,
                'is_edit' => $log->old_price !== null,
                'by' => $log->user?->name,
                'at' => $log->created_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'total' => $page->total(),
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
            ],
        ]);
    }

    /** @return array<string, string> */
    private function describeRecords(int $farmId, Collection $logs): array
    {
        $labels = [];

        $ids = fn (string $type) => $logs->where('record_type', $type)->pluck('record_id')->unique()->all();

        foreach (Expense::where('farm_id', $farmId)->whereIn('id', $ids('expense'))->get(['id', 'description']) as $e) {
            $labels["expense-{$e->id}"] = $e->description ?: 'مصروف';
        }
        foreach (FlockWaterLog::where('farm_id', $farmId)->whereIn('id', $ids('water_log'))->get(['id']) as $w) {
            $labels["water_log-{$w->id}"] = 'استهلاك ماء';
        }
        foreach (Sale::where('farm_id', $farmId)->whereIn('id', $ids('sale'))->get(['id', 'buyer_name']) as $s) {
            $labels["sale-{$s->id}"] = 'بيع: '.($s->buyer_name ?: 'بدون اسم');
        }

        return $labels;
    }
}
