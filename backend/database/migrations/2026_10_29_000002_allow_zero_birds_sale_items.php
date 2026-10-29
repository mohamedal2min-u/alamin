<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// قيد تصحيح خطأ في المبيعات: سطر وزن بدون طيور (birds_count = 0)
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        DB::statement('ALTER TABLE sale_items DROP CONSTRAINT IF EXISTS chk_sale_items_birds_count');
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT chk_sale_items_birds_count CHECK (birds_count >= 0)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        DB::statement('ALTER TABLE sale_items DROP CONSTRAINT IF EXISTS chk_sale_items_birds_count');
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT chk_sale_items_birds_count CHECK (birds_count > 0)');
    }
};
