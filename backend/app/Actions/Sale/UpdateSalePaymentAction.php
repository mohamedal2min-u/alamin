<?php

namespace App\Actions\Sale;

use App\Models\Sale;

class UpdateSalePaymentAction
{
    /**
     * @param  array<string, mixed>  $data  validated: received_amount, notes?
     */
    public function execute(Sale $sale, int $userId, array $data): Sale
    {
        $receivedAmount  = (float) $data['received_amount'];
        $remainingAmount = round($sale->net_amount - $receivedAmount, 2);

        $paymentStatus = match (true) {
            $remainingAmount <= 0  => 'paid',
            $receivedAmount > 0    => 'partial',
            default                => 'debt',
        };

        $sale->update([
            'received_amount'  => $receivedAmount,
            'remaining_amount' => $remainingAmount,
            'payment_status'   => $paymentStatus,
            'notes'            => $data['notes'] ?? $sale->notes,
            'updated_by'       => $userId,
        ]);

        return $sale->fresh('saleItems');
    }
}
