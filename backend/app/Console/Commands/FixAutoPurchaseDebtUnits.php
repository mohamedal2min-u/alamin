<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\InventoryTransaction;
use Illuminate\Console\Command;

class FixAutoPurchaseDebtUnits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-auto-purchase-debt-units';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate quantity/unit_price on auto-purchase debt expenses that were stored in the computed unit (e.g. kg) instead of the item\'s input unit (e.g. bags), using the linked inventory transaction as the source of truth';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $expenses = Expense::where('description', 'LIKE', 'دين شراء تلقائي:%')
            ->whereNotNull('linked_inventory_transaction_id')
            ->get();

        $fixed = 0;

        foreach ($expenses as $expense) {
            $txn = InventoryTransaction::find($expense->linked_inventory_transaction_id);

            if (! $txn || $txn->original_quantity === null) {
                continue;
            }

            $correctQty = (float) $txn->original_quantity;

            if (abs((float) $expense->quantity - $correctQty) < 0.0001) {
                continue;
            }

            $totalAmount      = (float) $expense->total_amount;
            $correctUnitPrice = ($totalAmount > 0 && $correctQty > 0) ? $totalAmount / $correctQty : 0;

            $expense->quantity   = $correctQty;
            $expense->unit_price = $correctUnitPrice;
            $expense->save();

            if ((float) $txn->total_amount > 0 && $correctQty > 0) {
                $txn->unit_price = $correctUnitPrice;
                $txn->save();
            }

            $fixed++;
            $this->info("Fixed expense #{$expense->id} ({$expense->description}) -> quantity {$correctQty}");
        }

        $this->info("Done. Fixed {$fixed} expense(s).");
    }
}
