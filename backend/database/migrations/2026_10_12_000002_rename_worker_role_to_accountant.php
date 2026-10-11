<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * دور العامل (worker) يتحول إلى المحاسب (accountant).
     * model_has_roles يشير إلى role_id فلا يحتاج تعديلاً.
     */
    public function up(): void
    {
        $this->rename('worker', 'accountant');
    }

    public function down(): void
    {
        $this->rename('accountant', 'worker');
    }

    private function rename(string $from, string $to): void
    {
        $roles = DB::table('roles')->where('name', $from)->get();

        foreach ($roles as $role) {
            $exists = DB::table('roles')
                ->where('name', $to)
                ->where('guard_name', $role->guard_name)
                ->where('farm_id', $role->farm_id)
                ->first();

            if ($exists) {
                // دور بالاسم الجديد موجود مسبقاً: انقل الربط إليه ثم احذف القديم
                DB::table('model_has_roles')->where('role_id', $role->id)->update(['role_id' => $exists->id]);
                DB::table('roles')->where('id', $role->id)->delete();
            } else {
                DB::table('roles')->where('id', $role->id)->update(['name' => $to]);
            }
        }
    }
};
