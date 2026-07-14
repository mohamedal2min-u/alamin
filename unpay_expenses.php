
$activeFlocks = \App\Models\Flock::where('status', 'active')->pluck('id');

$expenses = \App\Models\Expense::whereIn('flock_id', $activeFlocks)
    ->where('payment_status', 'paid')
    ->get();

$count = 0;
foreach ($expenses as $expense) {
    $expense->payment_status = 'unpaid';
    $expense->paid_amount = 0;
    $expense->remaining_amount = $expense->total_amount;
    $expense->save();
    $count++;
}

echo "Updated $count expenses to unpaid.\n";
