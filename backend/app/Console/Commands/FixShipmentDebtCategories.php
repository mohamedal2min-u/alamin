<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryTransaction;
use Illuminate\Console\Command;

class FixShipmentDebtCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-shipment-debt-categories';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recategorize inventory-shipment debt expenses that were filed under the generic "شراء مخزون" category instead of their item\'s real type (feed/medicine)';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $genericCategory = ExpenseCategory::whereNull('farm_id')->where('name', 'شراء مخزون')->first();

        if (! $genericCategory) {
            $this->info('No generic "شراء مخزون" category found, nothing to fix.');
            return;
        }

        $expenses = Expense::where('expense_category_id', $genericCategory->id)
            ->whereNotNull('linked_inventory_transaction_id')
            ->get();

        $fixed = 0;

        foreach ($expenses as $expense) {
            $item = InventoryTransaction::find($expense->linked_inventory_transaction_id)?->item;
            $code = $item?->itemType?->code;

            if (! $code) {
                continue;
            }

            $correctCategory = ExpenseCategory::where('code', $code)->whereNull('farm_id')->first();

            if (! $correctCategory) {
                continue;
            }

            $expense->expense_category_id = $correctCategory->id;
            $expense->save();
            $fixed++;

            $this->info("Recategorized expense #{$expense->id} ({$expense->description}) -> {$correctCategory->name}");
        }

        $this->info("Done. Recategorized {$fixed} expense(s).");
    }
}
