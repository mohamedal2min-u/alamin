<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * تصحيح بيانات لمرة واحدة (فوج الصيف، مزرعة 6) بطلب المالك:
 * العلف الباقي 2.5 طن خرج بسعر الشراء (610$ للطن) — 1 طن لزياد و1.5 طن لرائد خليل.
 * حركة التخفيض اليدوية الواحدة (2500 كغ) تُستبدل بقيدين مربوطين بالفوج ومسعّرين،
 * ليظهرا في سجلات العلف. نوعهما adjustment فلا يدخلان في تكلفة الاستهلاك.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $tx = DB::table('inventory_transactions')
                ->where(['farm_id' => 6, 'item_id' => 20, 'transaction_type' => 'adjustment', 'direction' => 'out'])
                ->where('computed_quantity', 2500)
                ->where('notes', 'like', '%زياد%')
                ->whereNull('flock_id')
                ->first();
            if (! $tx) {
                return;
            }

            $base = (array) $tx;
            unset($base['id']);
            $perKg = 0.61; // 610$ للطن
            foreach ([['زياد', 1000], ['رائد خليل', 1500]] as [$buyer, $kg]) {
                DB::table('inventory_transactions')->insert(array_merge($base, [
                    'flock_id'          => 3,
                    'source_module'     => 'feed_sale',
                    'original_quantity' => $kg / 50,
                    'computed_quantity' => $kg,
                    'unit_price'        => $perKg,
                    'total_amount'      => round($kg * $perKg, 2),
                    'notes'             => 'خروج علف لـ'.$buyer.': '.($kg / 1000).' طن ('.($kg / 50).' كيس) بسعر الشراء 610$ للطن — تم الدفع',
                    'updated_at'        => now(),
                ]));
            }
            DB::table('inventory_transactions')->where('id', $tx->id)->delete();
        });
    }

    public function down(): void
    {
        // تصحيح بيانات لا رجعة فيه
    }
};
