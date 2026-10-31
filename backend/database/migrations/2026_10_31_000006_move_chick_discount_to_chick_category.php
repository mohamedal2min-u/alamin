<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * بطلب المالك: قيد خصم الصيصان يكون في فئة الصوص نفسها (شراء كتاكيت)،
 * فتصير تكلفة الصوص 4125 − 150 = 3975. فئة «خصم من المورد» المؤقتة تُحذف.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $chicks = DB::table('expenses')->where(['id' => 1, 'farm_id' => 6, 'flock_id' => 3])->first();
            $cat = DB::table('expense_categories')->where(['farm_id' => 6, 'code' => 'supplier_discount'])->first();
            if (! $chicks || ! $cat) {
                return;
            }
            DB::table('expenses')
                ->where(['farm_id' => 6, 'expense_category_id' => $cat->id, 'is_discount' => true])
                ->update(['expense_category_id' => $chicks->expense_category_id, 'updated_at' => now()]);
            if (! DB::table('expenses')->where('expense_category_id', $cat->id)->exists()) {
                DB::table('expense_categories')->where('id', $cat->id)->delete();
            }
        });
    }

    public function down(): void
    {
    }
};
