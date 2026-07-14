
// Restore deleted items (Feed ID 30 and Medicine ID 23)
\App\Models\FlockFeedLog::insert([
    'id' => 30,
    'farm_id' => 6,
    'flock_id' => 3,
    'item_id' => 15, // I need to verify item_id for "علف مرحلة اولى شعريه"
    'entry_date' => '2026-07-14',
    'quantity' => 2,
    'unit_label' => 'كيس',
    'worker_id' => 8,
    'created_by' => 8,
    'updated_by' => 8,
    'created_at' => now(),
    'updated_at' => now(),
]);

\App\Models\FlockMedicine::insert([
    'id' => 23,
    'farm_id' => 6,
    'flock_id' => 3,
    'item_id' => 22,
    'entry_date' => '2026-07-14',
    'quantity' => 1,
    'unit_label' => 'لتر',
    'worker_id' => 8,
    'created_by' => 8,
    'updated_by' => 8,
    'created_at' => now(),
    'updated_at' => now(),
]);

echo "Restored\n";
