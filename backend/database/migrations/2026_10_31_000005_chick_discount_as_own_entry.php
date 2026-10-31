<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * خصم المورد كقيد مستقل (قيد دائن بقيمة سالبة يُنقص تكلفة الفوج)، بدل تعديل سعر الصوص.
 * 1) عمود is_discount، وقيود التحقق تسمح بالقيمة السالبة لقيود الخصم فقط.
 * 2) إرجاع قيد الصوص (رقم 1) إلى 7500 × 0.55 = 4125 كما في الفاتورة.
 * 3) قيد جديد «خصم صيصان» −150$ لفوج الصيف، مع صورة ورقة حساب اليمامة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('expenses', 'is_discount')) {
            Schema::table('expenses', function (Blueprint $table): void {
                $table->boolean('is_discount')->default(false);
            });
        }
        DB::statement('ALTER TABLE expenses DROP CONSTRAINT IF EXISTS chk_expenses_total_amount');
        DB::statement('ALTER TABLE expenses ADD CONSTRAINT chk_expenses_total_amount CHECK (total_amount >= 0 OR is_discount)');
        DB::statement('ALTER TABLE expenses DROP CONSTRAINT IF EXISTS chk_expenses_paid_amount');
        DB::statement('ALTER TABLE expenses ADD CONSTRAINT chk_expenses_paid_amount CHECK (paid_amount >= 0 OR is_discount)');

        DB::transaction(function () {
            $chicks = DB::table('expenses')->where(['id' => 1, 'farm_id' => 6, 'flock_id' => 3])->first();
            if (! $chicks || (float) $chicks->total_amount !== 3975.0) {
                return; // سبق التطبيق أو بيانات مختلفة
            }

            // 2) إرجاع سعر الصوص
            DB::table('expenses')->where('id', 1)->update([
                'unit_price' => 0.55, 'total_amount' => 4125, 'paid_amount' => 4125,
                'remaining_amount' => 0, 'payment_status' => 'paid', 'notes' => null, 'updated_at' => now(),
            ]);
            $p = DB::table('payments')->where(['farm_id' => 6, 'payable_type' => 'expense', 'payable_id' => 1])
                ->where('note', 'like', '%خصم صيصان%')->first();
            if ($p) {
                DB::table('payments')->where('id', $p->id)->update([
                    'amount' => (float) $p->amount + 150,
                    'note'   => trim(str_replace(['— ناقص خصم صيصان 150$', 'ناقص خصم صيصان 150$'], '', (string) $p->note)) ?: null,
                    'updated_at' => now(),
                ]);
            }

            // 3) فئة الخصم وقيده
            $cat = DB::table('expense_categories')->where(['farm_id' => 6, 'code' => 'supplier_discount'])->first();
            $catId = $cat->id ?? DB::table('expense_categories')->insertGetId([
                'farm_id' => 6, 'name' => 'خصم من المورد', 'code' => 'supplier_discount',
                'is_system' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);

            $id = DB::table('expenses')->insertGetId([
                'farm_id' => 6, 'flock_id' => 3, 'expense_category_id' => $catId,
                'entry_date' => '2026-08-08',
                'quantity' => 1, 'unit_price' => -150, 'total_amount' => -150,
                'paid_amount' => -150, 'remaining_amount' => 0, 'payment_status' => 'paid',
                'is_discount' => true,
                'description' => 'خصم صيصان من اليمامة',
                'notes' => 'حسب ورقة حساب اليمامة: خصم صيصان 150$ يُنقص تكلفة الصوص (فاتورة الصوص 7500 × 0.55 = 4125$ بقيت كما هي)',
                'created_by' => $chicks->created_by, 'created_at' => now(), 'updated_at' => now(),
            ]);

            // صورة ورقة الحساب مع القيد
            $sheet = DB::table('flock_documents')->where(['flock_id' => 3, 'id' => 1])->first();
            if ($sheet) {
                $doc = (array) $sheet;
                unset($doc['id']);
                DB::table('flock_documents')->insert(array_merge($doc, [
                    'title' => 'ورقة حساب اليمامة — خصم صيصان', 'record_type' => 'expense', 'record_id' => $id,
                    'created_at' => now(), 'updated_at' => now(),
                ]));
            }
        });
    }

    public function down(): void
    {
    }
};
