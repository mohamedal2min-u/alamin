
$cats = \App\Models\ExpenseCategory::all();
foreach ($cats as $cat) {
    echo $cat->id . " - " . $cat->name . "\n";
}
