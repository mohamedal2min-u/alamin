<?php

namespace App\Http\Controllers\Api\Flock;

use App\Actions\Flock\ShowFlockAction;
use App\Http\Controllers\Controller;
use App\Models\InventoryTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// علف خرج من مخزن الفوج لغيره (بيع بسعر الشراء) — يظهر في سجلات العلف ويُطرح من المشتريات
class FlockFeedOutflowController extends Controller
{
    public function __construct(
        private readonly ShowFlockAction $showFlockAction,
    ) {}

    // ── GET /api/flocks/{flock}/feed-outflows ─────────────────────────────────

    public function index(Request $request, int $flockId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        try {
            $flock = $this->showFlockAction->execute($farmId, $flockId);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $rows = InventoryTransaction::where('farm_id', $flock->farm_id)
            ->where('flock_id', $flock->id)
            ->where('direction', 'out')
            ->where('source_module', 'feed_sale')
            ->with('item:id,name')
            ->orderBy('transaction_date')
            ->get()
            ->map(fn (InventoryTransaction $t) => [
                'id'           => $t->id,
                'date'         => $t->transaction_date?->toDateString() ?? (string) $t->transaction_date,
                'item_name'    => $t->item?->name,
                'kg'           => (float) $t->computed_quantity,
                'total_amount' => (float) $t->total_amount,
                'notes'        => $t->notes,
            ]);

        return response()->json(['data' => $rows]);
    }
}
