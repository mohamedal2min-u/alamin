<?php

namespace App\Services;

use App\Models\Flock;
use App\Models\Expense;
use App\Models\InventoryTransaction;
use App\Models\Sale;
use App\Models\WarehouseItem;
use App\Models\FlockMortality;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportService
{
    /**
     * Get summary KPIs for the farm.
     */
    public function getSummaryKpis(int $farmId)
    {
        $flockCount = Flock::where('farm_id', $farmId)->count();
        $activeFlock = Flock::where('farm_id', $farmId)->where('status', 'active')->first();
        
        $totalSales = Sale::where('farm_id', $farmId)->sum('net_amount');
        
        $opExpenses = Expense::where('farm_id', $farmId)->sum('total_amount');
        $inventoryCost = $this->inventoryConsumptionCost($farmId);
        $chickCost = Flock::where('farm_id', $farmId)->sum('total_chick_cost');
        
        $totalExpenses = $opExpenses + $inventoryCost + $chickCost;

        $inventoryValue = WarehouseItem::where('farm_id', $farmId)
            ->selectRaw('SUM(current_quantity * COALESCE(average_cost, 0)) as total_value')
            ->value('total_value') ?? 0;

        return [
            'total_flocks_count' => (int) $flockCount,
            'active_flock_name' => $activeFlock ? $activeFlock->name : null,
            'active_flock_id' => $activeFlock ? $activeFlock->id : null,
            'total_sales' => (float) $totalSales,
            'total_expenses' => (float) $totalExpenses,
            'net_profit' => (float) ($totalSales - $totalExpenses),
            'inventory_value' => (float) $inventoryValue,
            'currency' => 'USD',
        ];
    }

    /**
     * Get detailed performance report for a specific flock.
     */
    public function getFlockReport(int $farmId, int $flockId)
    {
        $flock = Flock::with(['mortalities', 'feedLogs'])->where('farm_id', $farmId)->findOrFail($flockId);
        
        $mortalityCount = $flock->mortalities()->sum('quantity');

        // Feed from inventory transactions (most accurate source)
        $feedTxnData = DB::table('inventory_transactions')
            ->where('flock_id', $flockId)
            ->where('farm_id', $farmId)
            ->where('direction', 'out')
            ->where('transaction_type', 'consumption')
            ->where('source_module', 'flock_feed')
            ->selectRaw('SUM(original_quantity) as bags, SUM(computed_quantity) as kg_total')
            ->first();

        $totalFeedBags = (float) ($feedTxnData->bags ?? 0);
        $totalFeed     = (float) ($feedTxnData->kg_total ?? 0);

        // Feed cost = sum of total_amount from consumption transactions
        $feedCost = (float) DB::table('inventory_transactions')
            ->where('flock_id', $flockId)
            ->where('farm_id', $farmId)
            ->where('direction', 'out')
            ->where('transaction_type', 'consumption')
            ->where('source_module', 'flock_feed')
            ->sum('total_amount');

        $totalSales = $flock->sales()->sum('net_amount');
        
        $opExpenses = $flock->expenses()->sum('total_amount');
        $inventoryCost = $this->inventoryConsumptionCost($farmId, $flockId);
        $chickCost = (float) $flock->total_chick_cost;
        
        $totalExpenses = $opExpenses + $inventoryCost + $chickCost;
        
        // Sales statistics from sale_items
        $salesDetails = DB::table('sale_items')
            ->where('flock_id', $flockId)
            ->where('farm_id', $farmId)
            ->selectRaw('SUM(birds_count) as total_birds, SUM(total_weight_kg) as total_weight')
            ->first();

        $birdsSold = (int) ($salesDetails->total_birds ?? 0);
        $totalWeightSold = (float) ($salesDetails->total_weight ?? 0);
        $avgBirdWeight = $birdsSold > 0 ? round($totalWeightSold / $birdsSold, 2) : 0;

        // Specific expenses by common category names if available
        $medicineExpenses = $flock->expenses()
            ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->where(function($q) {
                $q->where('expense_categories.name', 'like', '%دواء%')
                  ->orWhere('expense_categories.name', 'like', '%علاج%')
                  ->orWhere('expense_categories.name', 'like', '%Medicine%');
            })
            ->sum('total_amount');

        $mortalityRate = $flock->initial_count > 0 
            ? round(($mortalityCount / $flock->initial_count) * 100, 2) 
            : 0;

        // Age Calculation: Source is current_age_days, fallback is calculation
        $ageDays = $flock->current_age_days;
        if ($ageDays === null) {
            $startDate = Carbon::parse($flock->start_date);
            $endDate = $flock->close_date ? Carbon::parse($flock->close_date) : Carbon::now();
            $ageDays = $startDate->diffInDays($endDate);
        }

        return [
            'flock_info' => [
                'id' => $flock->id,
                'name' => $flock->name,
                'start_date' => $flock->start_date ? $flock->start_date->format('Y-m-d') : null,
                'close_date' => $flock->close_date ? $flock->close_date->format('Y-m-d') : null,
                'status' => $flock->status,
                'initial_count' => (int) $flock->initial_count,
                'age_days' => (int) $ageDays,
            ],
            'performance' => [
                'mortality_count' => (int) $mortalityCount,
                'mortality_rate' => (float) $mortalityRate,
                'remaining_birds' => (int) ($flock->initial_count - $mortalityCount - $birdsSold),
                'total_feed_kg' => (float) $totalFeed,
                'total_feed_bags' => (float) $totalFeedBags,
                'feed_cost' => $feedCost,
                'total_medicine_cost' => (float) $medicineExpenses,
                'chick_cost' => $chickCost,
            ],
            'sales_analytics' => [
                'birds_sold' => $birdsSold,
                'total_weight_kg' => $totalWeightSold,
                'avg_bird_weight_kg' => $avgBirdWeight,
            ],
            'financial' => [
                'total_sales' => (float) $totalSales,
                'total_expenses' => (float) $totalExpenses,
                'profit_loss' => (float) ($totalSales - $totalExpenses),
                'is_profitable' => $totalSales >= $totalExpenses,
                'profit_status_label' => ($totalSales >= $totalExpenses) ? 'ربح' : 'خسارة',
            ],
            'details' => [
                'expense_records' => $flock->expenses()
                    ->with('expenseCategory:id,name')
                    ->orderByDesc('entry_date')
                    ->orderByDesc('id')
                    ->get()
                    ->map(fn($e) => [
                        'id' => $e->id,
                        'date' => $e->entry_date->toDateString(),
                        'category' => $e->expenseCategory?->name ?? 'غير محدد',
                        'amount' => (float) $e->total_amount,
                        'description' => $e->description,
                    ]),
                'sale_records' => $flock->sales()
                    ->orderByDesc('sale_date')
                    ->orderByDesc('id')
                    ->get()
                    ->map(fn($s) => [
                        'id' => $s->id,
                        'date' => $s->sale_date->toDateString(),
                        'category' => 'مبيعات',
                        'amount' => (float) $s->net_amount,
                        'description' => $s->buyer_name ? "مشتري: {$s->buyer_name}" : 'بيع نقدي',
                    ]),
            ],
        ];
    }

    /**
     * Get accounting summary and breakdown.
     */
    public function getAccountingSummary(int $farmId, $flockId = null, $startDate = null, $endDate = null)
    {
        $expensesQuery = Expense::where('farm_id', $farmId);
        $salesQuery = Sale::where('farm_id', $farmId);

        if ($flockId) {
            $expensesQuery->where('flock_id', $flockId);
            $salesQuery->where('flock_id', $flockId);
        }

        if ($startDate) {
            $expensesQuery->where('entry_date', '>=', $startDate);
            $salesQuery->where('sale_date', '>=', $startDate);
        }

        if ($endDate) {
            $expensesQuery->where('entry_date', '<=', $endDate);
            $salesQuery->where('sale_date', '<=', $endDate);
        }

        $opExpenses = $expensesQuery->sum('total_amount');
        $inventoryCost = $this->inventoryConsumptionCost($farmId, $flockId, $startDate, $endDate);
        
        // Calculate chick cost for relevant flocks
        $chickCostQuery = Flock::where('farm_id', $farmId);
        if ($flockId) {
            $chickCostQuery->where('id', $flockId);
        }
        if ($startDate) {
            $chickCostQuery->where('start_date', '>=', $startDate);
        }
        if ($endDate) {
            $chickCostQuery->where('start_date', '<=', $endDate);
        }
        $chickCost = (float) $chickCostQuery->sum('total_chick_cost');

        $totalExpenses = $opExpenses + $inventoryCost + $chickCost;
        $totalSales = $salesQuery->sum('net_amount');

        $totalPaidExpenses = $expensesQuery->sum('paid_amount');
        $totalReceivedSales = $salesQuery->sum('received_amount');

        // Breakdown by category — same filters as the main query
        $categoryQuery = Expense::where('expenses.farm_id', $farmId)
            ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->select('expense_categories.name as category', DB::raw('SUM(total_amount) as amount'))
            ->groupBy('expense_categories.name');

        if ($flockId) {
            $categoryQuery->where('expenses.flock_id', $flockId);
        }
        if ($startDate) {
            $categoryQuery->where('expenses.entry_date', '>=', $startDate);
        }
        if ($endDate) {
            $categoryQuery->where('expenses.entry_date', '<=', $endDate);
        }

        $expensesByCategory = $categoryQuery->get();

        // Inject Chick Purchase if applicable
        if ($chickCost > 0) {
            $expensesByCategory->push((object)[
                'category' => 'شراء صوص',
                'amount' => (float) $chickCost
            ]);
        }

        return [
            'summary' => [
                'total_sales' => (float) $totalSales,
                'total_expenses' => (float) $totalExpenses,
                'net_profit' => (float) ($totalSales - $totalExpenses),
            ],
            'cash_flow' => [
                'total_received' => (float) $totalReceivedSales,
                'total_paid' => (float) $totalPaidExpenses,
                'balance' => (float) ($totalReceivedSales - $totalPaidExpenses),
            ],
            'debts' => [
                'receivables' => (float) ($totalSales - $totalReceivedSales),
                'payables' => (float) ($totalExpenses - $totalPaidExpenses),
            ],
            'expense_breakdown' => $expensesByCategory,
            'currency' => 'USD'
        ];
    }

    /**
     * Get inventory summary and latest movements.
     */
    public function getInventoryReport(int $farmId)
    {
        // Current Stock Levels
        $stockLevels = WarehouseItem::where('warehouse_items.farm_id', $farmId)
            ->join('items', 'warehouse_items.item_id', '=', 'items.id')
            ->join('item_types', 'items.item_type_id', '=', 'item_types.id')
            ->select(
                'items.name',
                'item_types.name as type',
                'warehouse_items.current_quantity',
                'items.content_unit',
                'warehouse_items.average_cost'
            )
            ->get();

        // Latest Movements
        $latestMovements = \App\Models\InventoryTransaction::where('inventory_transactions.farm_id', $farmId)
            ->join('items', 'inventory_transactions.item_id', '=', 'items.id')
            ->select(
                'inventory_transactions.id',
                'inventory_transactions.transaction_date',
                'inventory_transactions.transaction_type',
                'inventory_transactions.direction',
                'items.name as item_name',
                'inventory_transactions.computed_quantity as quantity',
                'items.content_unit'
            )
            ->orderBy('inventory_transactions.transaction_date', 'desc')
            ->orderBy('inventory_transactions.id', 'desc')
            ->limit(10)
            ->get();

        return [
            'stock_levels' => $stockLevels,
            'latest_movements' => $latestMovements,
            'currency' => 'USD'
        ];
    }

    /**
     * Get partners summary, shares, and recent transactions.
     */
    public function getPartnersReport(int $farmId)
    {
        // 1. Partners and their current shares
        $partners = DB::table('partners')
            ->leftJoin('farm_partner_shares', function ($join) {
                $join->on('partners.id', '=', 'farm_partner_shares.partner_id')
                     ->where('farm_partner_shares.is_active', true);
            })
            ->where('partners.farm_id', $farmId)
            ->where('partners.status', 'active')
            ->select(
                'partners.id',
                'partners.name',
                'farm_partner_shares.share_percent'
            )
            ->get();

        // 2. Recent transactions (ledger)
        $transactions = \App\Models\PartnerTransaction::where('farm_id', $farmId)
            ->with('partner:id,name')
            ->orderBy('transaction_date', 'desc')
            ->limit(20)
            ->get();

        return [
            'partners' => $partners,
            'transactions' => $transactions,
            'currency' => 'USD'
        ];
    }

    /**
     * Get workers list and their associated expenses (salaries/payments).
     */
    public function getWorkersReport(int $farmId)
    {
        // 1. Get all workers linked to the farm
        $workers = DB::table('users')
            ->join('farm_users', 'users.id', '=', 'farm_users.user_id')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('farm_users.farm_id', $farmId)
            ->where('model_has_roles.farm_id', $farmId)
            ->where('model_has_roles.model_type', \App\Models\User::class)
            ->where('roles.name', 'worker')
            ->select('users.id', 'users.name', 'users.whatsapp', 'farm_users.joined_at')
            ->distinct()
            ->get();

        // 2. Aggregate expenses per worker in one query (avoid N+1)
        $expensesByWorker = DB::table('expenses')
            ->where('farm_id', $farmId)
            ->whereIn('worker_id', $workers->pluck('id'))
            ->selectRaw('worker_id, SUM(total_amount) as total_amount')
            ->groupBy('worker_id')
            ->pluck('total_amount', 'worker_id');

        $workerStats = $workers->map(fn ($worker) => [
            'id'             => $worker->id,
            'name'           => $worker->name,
            'whatsapp'       => $worker->whatsapp,
            'joined_at'      => $worker->joined_at,
            'total_payments' => (float) ($expensesByWorker[$worker->id] ?? 0),
        ]);

        return [
            'workers' => $workerStats,
            'currency' => 'USD'
        ];
    }

    /**
     * Sum the monetary cost of all consumption transactions (feed + medicine).
     * These are inventory outflows that represent a real cost but are not in the expenses table.
     */
    private function inventoryConsumptionCost(int $farmId, $flockId = null, $startDate = null, $endDate = null): float
    {
        $q = InventoryTransaction::where('farm_id', $farmId)
            ->where('direction', 'out')
            ->where('transaction_type', 'consumption')
            ->whereNotNull('total_amount');

        if ($flockId) {
            $q->where('flock_id', $flockId);
        }
        if ($startDate) {
            $q->where('transaction_date', '>=', $startDate);
        }
        if ($endDate) {
            $q->where('transaction_date', '<=', $endDate);
        }

        return (float) $q->sum('total_amount');
    }

    /**
     * Get all activities for a specific date.
     */
    public function getDailyReport(int $farmId, string $date = null)
    {
        $date = $date ?: date('Y-m-d');

        // Mortality
        $mortality = DB::table('flock_mortalities')
            ->where('farm_id', $farmId)
            ->where('entry_date', $date)
            ->sum('quantity');

        // Feed Consumption
        $feed = DB::table('flock_feed_logs')
            ->where('farm_id', $farmId)
            ->where('entry_date', $date)
            ->sum('quantity');

        // Expenses
        $expenses = DB::table('expenses')
            ->where('farm_id', $farmId)
            ->where('entry_date', $date)
            ->sum('total_amount');

        // Sales
        $sales = DB::table('sales')
            ->where('farm_id', $farmId)
            ->where('sale_date', $date)
            ->sum('net_amount');

        // Timeline of events
        $timeline = collect();

        // Add expenses to timeline
        DB::table('expenses')
            ->where('farm_id', $farmId)
            ->where('entry_date', $date)
            ->get()
            ->each(function ($item) use ($timeline) {
                $timeline->push([
                    'type' => 'expense',
                    'title' => 'مصروف جديد',
                    'detail' => $item->description ?: 'بدون وصف',
                    'amount' => $item->total_amount,
                    'time' => date('H:i', strtotime($item->created_at))
                ]);
            });

        // Add sales to timeline
        DB::table('sales')
            ->where('farm_id', $farmId)
            ->where('sale_date', $date)
            ->get()
            ->each(function ($item) use ($timeline) {
                $timeline->push([
                    'type' => 'sale',
                    'title' => 'عملية بيع',
                    'detail' => $item->buyer_name ?: 'مشتري غير محدد',
                    'amount' => $item->net_amount,
                    'time' => date('H:i', strtotime($item->created_at))
                ]);
            });

        return [
            'date' => $date,
            'summary' => [
                'mortality' => $mortality,
                'feed' => $feed,
                'expenses' => $expenses,
                'sales' => $sales
            ],
            'timeline' => $timeline->sortByDesc('time')->values(),
            'currency' => 'USD'
        ];
    }
}
