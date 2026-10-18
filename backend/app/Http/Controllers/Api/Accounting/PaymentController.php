<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StorePaymentRequest;
use App\Models\Payment;
use App\Services\PaymentLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * سجل الدفعات المؤرَّخ: كل دفعة صف مستقل يحدّث المدفوع والمتبقي وحالة الدفع.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly PaymentLedgerService $ledger) {}

    /** GET /accounting/payments?payable_type=&payable_id= */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payable_type' => ['required', Rule::in(Payment::PAYABLE_TYPES)],
            'payable_id' => ['required', 'integer'],
        ]);

        $payments = Payment::with('createdByUser:id,name')
            ->where('farm_id', $request->attributes->get('farm_id'))
            ->where('payable_type', $validated['payable_type'])
            ->where('payable_id', $validated['payable_id'])
            ->orderBy('paid_at')->orderBy('id')
            ->get()
            ->map(fn (Payment $p) => PaymentLedgerService::present($p));

        return response()->json(['data' => $payments]);
    }

    /**
     * POST /accounting/payments — للمحاسب فقط (أو مدير النظام).
     * يرفض تجاوز المتبقي، ويرفض أي دفعة على فوج مغلق. يعيد صف قائمة المراجعة المحدَّث.
     */
    public function store(StorePaymentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $row = $this->ledger->record(
            (int) $request->attributes->get('farm_id'),
            $data['payable_type'],
            (int) $data['payable_id'],
            $data,
            $request->user()->id,
        );

        return response()->json(['message' => 'تم تسجيل الدفعة', 'data' => $row], 201);
    }
}
