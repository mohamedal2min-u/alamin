<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * تصحيح بيانات لمرة واحدة (فوج الصيف، مزرعة 6) بطلب المالك:
 * ورقة حساب اليمامة فيها خصم صيصان 150$ على 7500 صوص (0.02$ للصوص).
 * سعر الصوص يصبح 0.53 بدل 0.55، والإجمالي 3975 بدل 4125.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $e = DB::table('expenses')
                ->where(['id' => 1, 'farm_id' => 6, 'flock_id' => 3])
                ->where('total_amount', 4125)
                ->first();
            if (! $e) {
                return;
            }

            $note = 'خصم صيصان 150$ من اليمامة حسب ورقة الحساب (7500 × 0.02). السعر قبل الخصم 0.55 = 4125$';
            DB::table('expenses')->where('id', 1)->update([
                'unit_price'       => 0.53,
                'total_amount'     => 3975,
                'paid_amount'      => 3975,
                'remaining_amount' => 0,
                'payment_status'   => 'paid',
                'notes'            => trim(($e->notes ? $e->notes."\n" : '').$note),
                'updated_at'       => now(),
            ]);

            // الدفعة المسجّلة 4125 تنقص 150 لتساوي الإجمالي الجديد
            $p = DB::table('payments')
                ->where(['farm_id' => 6, 'payable_type' => 'expense', 'payable_id' => 1])
                ->orderByDesc('amount')
                ->first();
            if ($p && (float) $p->amount >= 150) {
                DB::table('payments')->where('id', $p->id)->update([
                    'amount'     => (float) $p->amount - 150,
                    'note'       => trim(($p->note ? $p->note.' — ' : '').'ناقص خصم صيصان 150$'),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
    }
};
