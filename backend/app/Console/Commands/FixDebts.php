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
    protected $signature = 'app:fix-debts {--force : Actually write the changes. Without this flag the command only reports what it WOULD change.}';

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

        $force = (bool) $this->option('force');
        if (! $force) {
            $this->warn("Dry-run mode (no --force): reporting what WOULD change without writing anything.");
            $this->warn("This command force-resets payment_status/paid_amount to unpaid/0 for EVERY matching row,");
            $this->warn("including rows that are already legitimately paid/partial — re-run with --force only");
            $this->warn("after confirming the reported rows are actually corrupted, not real payment history.");
        }

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
                $matching = DB::table($table)
                    ->where('flock_id', $flock->id)
                    ->where('payment_status', '!=', 'unpaid');

                if ($force) {
                    $updateData = [
                        'payment_status' => 'unpaid',
                        'paid_amount' => 0
                    ];
                    if (\Schema::hasColumn($table, 'remaining_amount')) {
                        $updateData['remaining_amount'] = DB::raw('total_amount');
                    }

                    $count = $matching->update($updateData);
                    $this->info("Updated {$count} records in {$table} to unpaid.");
                } else {
                    $count = $matching->count();
                    $this->info("Would update {$count} records in {$table} to unpaid (dry-run).");
                }
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
                    if ($force) {
                        DB::table($table)->where('id', $record->id)->update($updates);
                    }
                    $fixed++;
                }
            }
            if ($fixed > 0) {
                $this->info(($force ? "Fixed" : "Would fix") . " Arabic digits in {$fixed} records of {$table}.");
            }
        }

        $this->info($force ? "Done fixing debts." : "Dry-run complete. Re-run with --force to apply.");
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
