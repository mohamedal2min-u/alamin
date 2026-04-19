<?php

namespace App\Http\Controllers\Api\Sale;

use App\Actions\Flock\ShowFlockAction;
use App\Actions\Sale\CreateSaleAction;
use App\Actions\Sale\UpdateSalePaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\StoreSaleRequest;
use App\Http\Requests\Sale\UpdateSalePaymentRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SaleController extends Controller
{
    public function __construct(
        private readonly ShowFlockAction        $showFlockAction,
        private readonly CreateSaleAction       $createAction,
        private readonly UpdateSalePaymentAction $updatePaymentAction,
    ) {}

    // ── GET /api/flocks/{flock}/sales ─────────────────────────────────────────

    public function index(Request $request, int $flockId): ResourceCollection|JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        try {
            $this->showFlockAction->execute($farmId, $flockId);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 404);
        }

        $sales = Sale::where('farm_id', $farmId)
            ->where('flock_id', $flockId)
            ->with('saleItems')
            ->orderByDesc('sale_date')
            ->get();

        return SaleResource::collection($sales);
    }

    // ── POST /api/flocks/{flock}/sales ────────────────────────────────────────

    public function store(StoreSaleRequest $request, int $flockId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $userId = $request->user()->id;

        try {
            $flock = $this->showFlockAction->execute($farmId, $flockId);
            $sale  = $this->createAction->execute($flock, $userId, $request->validated());
        } catch (\Exception $e) {
            $code = (int) $e->getCode();
            return response()->json(
                ['message' => $e->getMessage()],
                $code >= 400 && $code < 600 ? $code : 422
            );
        }

        return response()->json([
            'message' => 'تم تسجيل البيع بنجاح',
            'data'    => new SaleResource($sale),
        ], 201);
    }

    // ── GET /api/sales ────────────────────────────────────────────────────────

    public function indexAll(Request $request): ResourceCollection
    {
        $farmId  = $request->attributes->get('farm_id');
        $flockId = $request->query('flock_id');

        $query = Sale::where('farm_id', $farmId)->with('saleItems');

        if ($flockId) {
            $query->where('flock_id', (int) $flockId);
        }

        return SaleResource::collection($query->orderByDesc('sale_date')->get());
    }

    // ── GET /api/sales/{sale} ─────────────────────────────────────────────────

    public function show(Request $request, int $saleId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        $sale = Sale::where('farm_id', $farmId)
            ->with('saleItems')
            ->find($saleId);

        if (! $sale) {
            return response()->json(['message' => 'سجل البيع غير موجود'], 404);
        }

        return response()->json(['data' => new SaleResource($sale)]);
    }

    // ── PATCH /api/sales/{sale}/payment ───────────────────────────────────────

    public function updatePayment(UpdateSalePaymentRequest $request, int $saleId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $userId = $request->user()->id;

        $sale = Sale::where('farm_id', $farmId)->find($saleId);

        if (! $sale) {
            return response()->json(['message' => 'سجل البيع غير موجود'], 404);
        }

        $sale = $this->updatePaymentAction->execute($sale, $userId, $request->validated());

        return response()->json([
            'message' => 'تم تحديث حالة الدفع بنجاح',
            'data'    => new SaleResource($sale),
        ]);
    }
}
