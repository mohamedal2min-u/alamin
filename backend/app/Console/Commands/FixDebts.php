<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Flock;

class FixDebts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-debts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix existing active flock debts by converting them to unpaid and removing arabic digits';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $flock = Flock::where('status', 'active')->first();
        if (!$flock) {
            $this->error("No active flock found.");
            return;
        }

        $this->info("Active flock ID: " . $flock->id);

        $tables = [
            'expenses',
            'water_logs',
            'inventory_transactions',
        ];

        foreach ($tables as $table) {
            if (!\Schema::hasTable($table)) {
                continue;
            }

            // 1. Convert to unpaid
            if (\Schema::hasColumn($table, 'payment_status')) {
                $count = DB::table($table)
                    ->where('flock_id', $flock->id)
                    ->where('payment_status', '!=', 'unpaid')
                    ->update([
                        'payment_status' => 'unpaid',
                        'paid_amount' => 0
                    ]);
                $this->info("Updated {$count} records in {$table} to unpaid.");
            }

            // 2. Fix arabic digits
            $records = DB::table($table)->where('flock_id', $flock->id)->get();
            $fixed = 0;
            foreach ($records as $record) {
                $updates = [];
                foreach (['total_amount', 'paid_amount', 'quantity', 'price'] as $col) {
                    if (isset($record->$col)) {
                        $eng = $this->toEnglishDigits((string)$record->$col);
                        if ($eng !== (string)$record->$col) {
                            $updates[$col] = is_numeric($eng) ? $eng : $record->$col;
                        }
                    }
                }

                if (!empty($updates)) {
                    DB::table($table)->where('id', $record->id)->update($updates);
                    $fixed++;
                }
            }
            if ($fixed > 0) {
                $this->info("Fixed Arabic digits in {$fixed} records of {$table}.");
            }
        }

        $this->info("Done fixing debts.");
    }

    private function toEnglishDigits($str) {
        $arabic = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $english = ['0','1','2','3','4','5','6','7','8','9'];
        $str = str_replace($arabic, $english, $str);
        $str = str_replace($persian, $english, $str);
        return $str;
    }
}
