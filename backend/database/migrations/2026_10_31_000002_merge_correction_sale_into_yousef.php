<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * تصحيح بيانات لمرة واحدة (فوج الصيف، مزرعة 6) بطلب المالك:
 * وزن يوسف الحقيقي حسب ورقة الحساب = 17045 − 7168 (هاشم) − 6402 (أبو درويش) = 3475 كغ.
 * يُدمج «قيد تصحيح الخطأ» (بيع 7: 417 كغ بلا طيور) في بيع يوسف (بيع 4: 3058 كغ) ثم يُحذف.
 * لا يفعل شيئاً إن لم تطابق السجلات الحالة المتوقعة تماماً.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::transaction(function () {
            $yousef = DB::table('sales')->where(['id' => 4, 'farm_id' => 6, 'flock_id' => 3])->lockForUpdate()->first();
            $fix    = DB::table('sales')->where(['id' => 7, 'farm_id' => 6, 'flock_id' => 3])->lockForUpdate()->first();
            if (! $yousef || ! $fix) {
                return;
            }
            $yItems = DB::table('sale_items')->where('sale_id', 4)->get();
            $fItems = DB::table('sale_items')->where('sale_id', 7)->get();
            if ($yItems->count() !== 1 || $fItems->count() !== 1
                || (float) $yItems[0]->total_weight_kg !== 3058.0
                || (float) $fItems[0]->total_weight_kg !== 417.0
                || (int) $fItems[0]->birds_count !== 0) {
                return;
            }

            $item   = $yItems[0];
            $weight = 3475.0;
            $price  = (float) $item->unit_price_per_kg; // 2.4
            $line   = round($weight * $price, 2);       // 8340

            DB::table('sale_items')->where('id', $item->id)->update([
                'total_weight_kg' => $weight,
                'avg_weight_kg'   => round($weight / max(1, (int) $item->birds_count), 3),
                'line_total'      => $line,
                'updated_at'      => now(),
            ]);

            // دفعات قيد التصحيح تنتقل إلى بيع يوسف
            DB::table('payments')->where(['payable_type' => 'sale', 'payable_id' => 7])->update(['payable_id' => 4]);

            $received  = round((float) $yousef->received_amount + (float) $fix->received_amount, 2);
            $net       = round($line - (float) $yousef->discount_amount, 2);
            $remaining = max(0, round($net - $received, 2));
            DB::table('sales')->where('id', 4)->update([
                'gross_amount'     => $line,
                'net_amount'       => $net,
                'received_amount'  => $received,
                'remaining_amount' => $remaining,
                'payment_status'   => $remaining <= 0 ? 'paid' : ($received > 0 ? 'partial' : 'debt'),
                'notes'            => trim(($yousef->notes ? $yousef->notes."\n" : '')
                    .'الوزن حسب ورقة الحساب: 17045 − 7168 هاشم − 6402 أبو درويش = 3475 كغ (كان مسجّلاً 3058، وأُضيف فرق 417 كغ).'),
                'updated_at'       => now(),
            ]);

            DB::table('flock_documents')->where(['record_type' => 'sale', 'record_id' => 7])->delete();
            DB::table('sale_items')->where('sale_id', 7)->delete();
            DB::table('sales')->where('id', 7)->delete();
        });
    }

    public function down(): void
    {
        // تصحيح بيانات لا رجعة فيه
    }
};
