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

            // Auto-create the target category if it's missing instead of silently skipping —
            // previously this left every feed/medicine shipment debt stuck under the generic
            // category forever whenever the real category hadn't been seeded yet.
            $correctCategory = ExpenseCategory::firstOrCreate(
                ['code' => $code, 'farm_id' => null],
                ['name' => $item?->itemType?->name ?? $code, 'is_system' => true, 'is_active' => true]
            );

            $expense->expense_category_id = $correctCategory->id;
            $expense->save();
            $fixed++;

            $this->info("Recategorized expense #{$expense->id} ({$expense->description}) -> {$correctCategory->name}");
        }

        $this->info("Done. Recategorized {$fixed} expense(s).");
    }
}
