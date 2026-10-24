<?php

namespace App\Actions\Sale;

use App\Models\Sale;
use App\Services\PriceAuditService;
use Illuminate\Support\Facades\DB;

class UpdateSalePaymentAction
{
    /**
     * @param  array<string, mixed>  $data  validated: received_amount, notes?
     */
    public function __construct(private readonly PriceAuditService $audit) {}

    public function execute(Sale $sale, int $userId, array $data): Sale
    {
        return DB::transaction(function () use ($sale, $userId, $data): Sale {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $receivedAmount = (float) $data['received_amount'];

            if ($receivedAmount > (float) $sale->net_amount) {
                throw new \Exception('المبلغ المستلم أكبر من صافي قيمة البيع — لا يمكن أن تتجاوز الدفعة المبلغ المستحق', 422);
            }

            $remainingAmount = round($sale->net_amount - $receivedAmount, 2);

            $paymentStatus = match (true) {
                $remainingAmount <= 0 => 'paid',
                $receivedAmount > 0   => 'partial',
                default               => 'debt',
            };

            $oldReceived = (float) $sale->received_amount;

            $sale->update([
                'received_amount'  => $receivedAmount,
                'remaining_amount' => $remainingAmount,
                'payment_status'   => $paymentStatus,
                'notes'            => $data['notes'] ?? $sale->notes,
                'updated_by'       => $userId,
            ]);

            $this->audit->recordPayment($sale->farm_id, $userId, 'sale', $sale->id, $oldReceived, $receivedAmount);

            // الفرق يُسجَّل دفعةً مؤرَّخة في سجل الدفعات
            app(\App\Services\PaymentLedgerService::class)->sync($sale, now()->toDateString(), 'cash', 'تعديل المبلغ المستلم', $userId);

            return $sale->fresh('saleItems');
        });
    }
}
