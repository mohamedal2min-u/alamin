<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * بطلب المالك:
 * - العلف الباقي بعناه نحن: طن لزياد (610) وطن ونص لرائد خليل (915) بسعر الشراء.
 *   لكل واحد ملف تاجر فيه البيع والقبض. مبلغ زياد قبضته اليمامة وخصمته لنا بحسابها.
 * - باقي حساب اليمامة 1,898 تاريخه نفس تاريخ المحاسبة على الورقة (7/9).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $yamama = DB::table('contacts')->where(['farm_id' => 6, 'name' => 'اليمامة'])->value('id');
            if (! $yamama) {
                return;
            }
            $now = now();
            $user = DB::table('expenses')->where('id', 1)->value('created_by');

            DB::table('payments')->where(['farm_id' => 6, 'contact_id' => $yamama, 'payable_type' => 'sale', 'amount' => 1898])
                ->update(['paid_at' => '2026-09-07', 'note' => 'باقي حساب اليمامة 1,898 «لنا» — مستلم بتاريخ المحاسبة 7/9', 'updated_at' => $now]);

            DB::table('expenses')->where(['farm_id' => 6, 'contact_id' => $yamama, 'total_amount' => -610])
                ->update(['description' => 'ثمن طن علف بعناه لزياد — قبضته اليمامة', 'updated_at' => $now]);

            $buyers = [
                ['زياد', 'زياد', 'بعنا طن علف لزياد بسعر الشراء 610$ — المبلغ قبضته اليمامة وخصمته لنا بحسابها'],
                ['رائد خليل', 'رائد خليل', 'بعنا طن ونص علف لرائد خليل بسعر الشراء 915$ — قبضناه منه'],
            ];
            foreach ($buyers as [$name, $match, $note]) {
                $tx = DB::table('inventory_transactions')
                    ->where(['farm_id' => 6, 'flock_id' => 3, 'source_module' => 'feed_sale'])
                    ->where('notes', 'like', '%'.$match.'%')->whereNull('contact_id')->first();
                if (! $tx) {
                    continue;
                }
                $cid = DB::table('contacts')->where(['farm_id' => 6, 'name' => $name])->value('id')
                    ?? DB::table('contacts')->insertGetId([
                        'farm_id' => 6, 'name' => $name, 'type' => 'buyer', 'is_active' => true,
                        'notes' => 'اشترى علف من فوج الصيف بسعر الشراء',
                        'created_by' => $user, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                DB::table('inventory_transactions')->where('id', $tx->id)->update([
                    'contact_id' => $cid, 'notes' => $note, 'paid_amount' => $tx->total_amount, 'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
    }
};
