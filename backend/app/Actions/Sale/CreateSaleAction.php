<?php

namespace App\Actions\Sale;

use App\Models\Flock;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;

class CreateSaleAction
{
    /**
     * @param  array<string, mixed>  $data  validated: sale_date, buyer_name?, reference_no?,
     *                                       discount_amount?, received_amount?, notes?, items[]
     * @throws \Exception 422 if flock is not active
     */
    public function execute(Flock $flock, int $userId, array $data): Sale
    {
        if ($flock->status !== 'active') {
            throw new \Exception('لا يمكن تسجيل بيع على فوج غير نشط', 422);
        }

        return DB::transaction(function () use ($flock, $userId, $data): Sale {
            $discountAmount  = (float) ($data['discount_amount'] ?? 0);
            $receivedAmount  = (float) ($data['received_amount'] ?? 0);

            // ── حساب المبالغ من السطور ────────────────────────────────────────
            $grossAmount = 0.0;
            $saleLines   = [];

            foreach ($data['items'] as $item) {
                $birdsCount      = (int) $item['birds_count'];
                $totalWeightKg   = (float) $item['total_weight_kg'];
                $unitPricePerKg  = (float) $item['unit_price_per_kg'];
                $avgWeightKg     = $birdsCount > 0 ? round($totalWeightKg / $birdsCount, 3) : null;
                $lineTotal       = round($totalWeightKg * $unitPricePerKg, 2);

                $grossAmount += $lineTotal;

                $saleLines[] = [
                    'farm_id'         => $flock->farm_id,
                    'flock_id'        => $flock->id,
                    'birds_count'     => $birdsCount,
                    'total_weight_kg' => $totalWeightKg,
                    'avg_weight_kg'   => $avgWeightKg,
                    'unit_price_per_kg' => $unitPricePerKg,
                    'line_total'      => $lineTotal,
                    'notes'           => $item['notes'] ?? null,
                ];
            }

            $netAmount       = round($grossAmount - $discountAmount, 2);
            $remainingAmount = round($netAmount - $receivedAmount, 2);

            $paymentStatus = match (true) {
                $remainingAmount <= 0           => 'paid',
                $receivedAmount > 0             => 'partial',
                default                         => 'debt',
            };

            // ── إنشاء سجل البيع ───────────────────────────────────────────────
            $sale = Sale::create([
                'farm_id'                  => $flock->farm_id,
                'flock_id'                 => $flock->id,
                'sale_date'                => $data['sale_date'],
                'reference_no'             => $data['reference_no'] ?? null,
                'buyer_name'               => $data['buyer_name'] ?? null,
                'invoice_attachment_path'  => null,
                'gross_amount'             => $grossAmount,
                'discount_amount'          => $discountAmount,
                'net_amount'               => $netAmount,
                'received_amount'          => $receivedAmount,
                'remaining_amount'         => $remainingAmount,
                'payment_status'           => $paymentStatus,
                'notes'                    => $data['notes'] ?? null,
                'created_by'               => $userId,
                'updated_by'               => $userId,
            ]);

            // ── إنشاء سطور البيع ──────────────────────────────────────────────
            foreach ($saleLines as $line) {
                SaleItem::create(array_merge($line, ['sale_id' => $sale->id]));
            }

            return $sale->load('saleItems');
        });
    }
}
