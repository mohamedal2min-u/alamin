<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Flock;
use App\Models\InventoryTransaction;
use Illuminate\Console\Command;

class SyncMissingShipmentDebts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-missing-shipment-debts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create missing review-queue debt entries for old manual feed/medicine shipments that were recorded in inventory but never produced a debt expense, and correct their stale payment_status';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $linkedTxnIds = Expense::whereNotNull('linked_inventory_transaction_id')
            ->pluck('linked_inventory_transaction_id');

        $orphanPurchases = InventoryTransaction::where('transaction_type', 'purchase')
            ->where('source_module', 'manual')
            ->whereNotIn('id', $linkedTxnIds)
            ->with('item.itemType')
            ->get();

        $created = 0;

        foreach ($orphanPurchases as $txn) {
            $item = $txn->item;
            $code = $item?->itemType?->code;

            if (! in_array($code, ['feed', 'medicine'], true)) {
                continue;
            }

            $totalAmount  = $txn->total_amount !== null ? (float) $txn->total_amount : null;
            $paidAmount   = (float) ($txn->paid_amount ?? 0);
            $missingPrice = $totalAmount === null || $totalAmount <= 0;
            $remaining    = $totalAmount !== null ? max(0, $totalAmount - $paidAmount) : 0;

            if (! ($remaining > 0 || $missingPrice)) {
                continue; // already fully paid, no debt needed
            }

            $category = ExpenseCategory::where('code', $code)->whereNull('farm_id')->first();

            if (! $category) {
                continue;
            }

            $flockId = $txn->flock_id
                ?? Flock::where('farm_id', $txn->farm_id)->where('status', 'active')->value('id');

            $debtStatus = $missingPrice ? 'unpaid' : ($paidAmount > 0 ? 'partial' : 'unpaid');

            Expense::create([
                'farm_id'                         => $txn->farm_id,
                'flock_id'                         => $flockId,
                'expense_category_id'              => $category->id,
                'entry_date'                       => $txn->transaction_date,
                'description'                      => 'دين شراء: ' . $item->name . (! empty($txn->supplier_name) ? ' — ' . $txn->supplier_name : ''),
                'quantity'                         => $txn->original_quantity,
                'unit_price'                       => $txn->unit_price,
                'total_amount'                     => $totalAmount ?? 0,
                'paid_amount'                      => min($paidAmount, $totalAmount ?? 0),
                'remaining_amount'                 => $missingPrice ? 0 : $remaining,
                'payment_status'                   => $debtStatus,
                'reference_no'                     => $txn->invoice_no,
                'linked_inventory_transaction_id'  => $txn->id,
                'created_by'                       => $txn->created_by,
                'updated_by'                       => $txn->updated_by,
            ]);

            $txn->payment_status = $debtStatus;
            if (! $txn->flock_id) {
                $txn->flock_id = $flockId;
            }
            $txn->save();

            $created++;
            $this->info("Created debt for transaction #{$txn->id} ({$item->name})");
        }

        $this->info("Done. Created {$created} missing debt(s).");
    }
}
