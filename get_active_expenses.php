
$activeFlocks = \App\Models\Flock::where('status', 'active')->pluck('id');
$expenses = \App\Models\Expense::whereIn('flock_id', $activeFlocks)->get(['id', 'payment_status', 'description', 'total_amount', 'paid_amount']);
echo json_encode($expenses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
