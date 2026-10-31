<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * ملف المورد «اليمامة» لفوج الصيف (مزرعة 6) مطابق لورقة حساب اليمامة بالتفصيل:
 *
 *   علينا لهم: صوص 4125، خصم صيصان −150، علف 29 طن 17690، لقاح شرب 375، أدوية 2500،
 *              أجار ورشة 45، فورمالين 35، طن علف زياد رجع بحسابهم −610  ⇒ 24,010
 *   لنا عليهم: مبيعات 40,908 (قبضوها هم من التجار)
 *   مقاصة: 24,010 من ثمن المبيعات، ودفعتان نقداً 10,000 و5,000 (7/9)، والباقي 1,898 استلمناه (بتأكيد المالك) ⇒ الرصيد صفر
 *
 * المقاصة تُسجَّل دفعةً على المصاريف (مدفوعة من ثمن المبيعات) وقبضاً على المبيعات بنفس المبلغ،
 * فلا تتغيّر أرباح الفوج. قيد −610 مربوط بحركة خروج طن زياد فلا يدخل في الربح مرتين.
 */
return new class extends Migration
{
    private const FARM = 6;
    private const FLOCK = 3;
    private const EXPENSES = [1, 150, 113, 4, 5, 70, 91, 109, 141, 146, 147, 148, 149];

    public function up(): void
    {
        DB::transaction(function () {
            if (DB::table('contacts')->where(['farm_id' => self::FARM, 'name' => 'اليمامة'])->exists()) {
                return;
            }
            $sum = (float) DB::table('expenses')->whereIn('id', self::EXPENSES)->where('flock_id', self::FLOCK)->sum('total_amount');
            $sales = DB::table('sales')->where(['farm_id' => self::FARM, 'flock_id' => self::FLOCK])->orderBy('sale_date')->orderBy('id')->get();
            if (abs($sum - 24620) > 0.01 || abs((float) $sales->sum('net_amount') - 40908) > 0.01) {
                return; // البيانات تغيّرت عن الورقة: لا نلمس شيئاً
            }
            $user = DB::table('expenses')->where('id', 1)->value('created_by');
            $now = now();

            $cid = DB::table('contacts')->insertGetId([
                'farm_id' => self::FARM, 'name' => 'اليمامة', 'type' => 'both',
                'notes' => 'Yamama For Feed and Poultry — مورد الصوص والعلف والأدوية، وقبض ثمن المبيعات من التجار. حساب فوج الصيف حسب ورقة اليمامة.',
                'is_active' => true, 'created_by' => $user, 'created_at' => $now, 'updated_at' => $now,
            ]);

            // طن زياد: رجع بحساب اليمامة (610) — قيد دائن مربوط بحركة الخروج
            $ziadTx = DB::table('inventory_transactions')->where(['farm_id' => self::FARM, 'flock_id' => self::FLOCK, 'source_module' => 'feed_sale'])
                ->where('notes', 'like', '%زياد%')->value('id');
            $feedCat = DB::table('expenses')->where('id', 113)->value('expense_category_id');
            $ziadId = DB::table('expenses')->insertGetId([
                'farm_id' => self::FARM, 'flock_id' => self::FLOCK, 'expense_category_id' => $feedCat,
                'contact_id' => $cid, 'entry_date' => '2026-08-08',
                'quantity' => 1, 'unit_price' => -610, 'total_amount' => -610,
                'paid_amount' => -610, 'remaining_amount' => 0, 'payment_status' => 'paid', 'is_discount' => true,
                'description' => 'طن علف لزياد — رجع بحساب اليمامة',
                'notes' => 'حسب ورقة اليمامة: «610 طن علف» تُخصم لنا. العلف خرج لزياد بسعر الشراء.',
                'linked_inventory_transaction_id' => $ziadTx,
                'created_by' => $user, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $expenseIds = array_merge(self::EXPENSES, [$ziadId]);

            DB::table('expenses')->whereIn('id', $expenseIds)->update(['contact_id' => $cid, 'updated_at' => $now]);
            $linkedTx = DB::table('expenses')->whereIn('id', $expenseIds)->whereNotNull('linked_inventory_transaction_id')
                ->where('id', '!=', $ziadId)->pluck('linked_inventory_transaction_id');
            DB::table('inventory_transactions')->whereIn('id', $linkedTx)->update(['contact_id' => $cid, 'updated_at' => $now]);

            // المصاريف: دفعتها السابقة تصير «مقاصة من ثمن المبيعات» بتاريخ الورقة
            DB::table('payments')->where('payable_type', 'expense')->whereIn('payable_id', $expenseIds)->delete();
            $payable = 0.0;
            foreach (DB::table('expenses')->whereIn('id', $expenseIds)->get() as $e) {
                $payable += (float) $e->total_amount;
                DB::table('payments')->insert([
                    'farm_id' => self::FARM, 'contact_id' => $cid, 'payable_type' => 'expense', 'payable_id' => $e->id,
                    'amount' => $e->total_amount, 'paid_at' => '2026-08-08', 'method' => 'other',
                    'note' => 'مقاصة من ثمن المبيعات بحساب اليمامة', 'is_opening' => false,
                    'created_by' => $user, 'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('expenses')->where('id', $e->id)->update([
                    'paid_amount' => $e->total_amount, 'remaining_amount' => 0, 'payment_status' => 'paid',
                ]);
            }
            $payable = round($payable, 2); // 24,010

            // المبيعات: قبضتها اليمامة؛ تُسدَّد لنا مقاصةً ثم نقداً حتى آخر قرش
            DB::table('sales')->whereIn('id', $sales->pluck('id'))->update(['contact_id' => $cid, 'updated_at' => $now]);
            DB::table('payments')->where('payable_type', 'sale')->whereIn('payable_id', $sales->pluck('id'))->delete();
            $pool = [
                ['amount' => $payable, 'date' => '2026-08-08', 'method' => 'other', 'note' => 'مقاصة: تكاليف اليمامة (صوص وعلف وأدوية) من ثمن المبيعات'],
                ['amount' => 10000, 'date' => '2026-08-08', 'method' => 'cash', 'note' => 'دفعة سابقة من اليمامة 10,000 (حسب الورقة)'],
                ['amount' => 5000, 'date' => '2026-09-07', 'method' => 'cash', 'note' => 'دفعة من اليمامة 5,000 بتاريخ 7/9 (حسب الورقة)'],
                ['amount' => 1898, 'date' => '2026-10-31', 'method' => 'cash', 'note' => 'باقي حساب اليمامة 1,898 «لنا» آخر الورقة — مستلم (تاريخ الاستلام الفعلي غير مسجّل)'],
            ];
            $p = 0;
            foreach ($sales as $s) {
                $need = (float) $s->net_amount;
                $got = 0.0;
                while ($need - $got > 0.005 && $p < count($pool)) {
                    $take = round(min($pool[$p]['amount'], $need - $got), 2);
                    DB::table('payments')->insert([
                        'farm_id' => self::FARM, 'contact_id' => $cid, 'payable_type' => 'sale', 'payable_id' => $s->id,
                        'amount' => $take, 'paid_at' => $pool[$p]['date'], 'method' => $pool[$p]['method'],
                        'note' => $pool[$p]['note'], 'is_opening' => false,
                        'created_by' => $user, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                    $got = round($got + $take, 2);
                    $pool[$p]['amount'] = round($pool[$p]['amount'] - $take, 2);
                    if ($pool[$p]['amount'] <= 0.005) {
                        $p++;
                    }
                }
                $remaining = round($need - $got, 2);
                DB::table('sales')->where('id', $s->id)->update([
                    'received_amount' => $got, 'remaining_amount' => $remaining,
                    'payment_status' => $remaining <= 0 ? 'paid' : ($got > 0 ? 'partial' : 'debt'),
                ]);
            }

            // صورة ورقة الحساب مع قيد طن زياد
            $sheet = DB::table('flock_documents')->where(['flock_id' => self::FLOCK, 'id' => 1])->first();
            if ($sheet) {
                $doc = (array) $sheet;
                unset($doc['id']);
                DB::table('flock_documents')->insert(array_merge($doc, [
                    'title' => 'ورقة حساب اليمامة — طن علف زياد', 'record_type' => 'expense', 'record_id' => $ziadId,
                    'created_at' => $now, 'updated_at' => $now,
                ]));
            }
        });
    }

    public function down(): void
    {
    }
};
