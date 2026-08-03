<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            // الوزن القائم = وزن الطيور + الأقفاص كما يُقرأ من الميزان مباشرة.
            // total_weight_kg يبقى الوزن الصافي (القائم - وزن الأقفاص) المستخدم في التسعير.
            $table->integer('crates_count')->nullable()->after('total_weight_kg');
            $table->decimal('crate_weight_kg', 10, 3)->nullable()->after('crates_count');
            $table->decimal('gross_weight_kg', 14, 3)->nullable()->after('crate_weight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['crates_count', 'crate_weight_kg', 'gross_weight_kg']);
        });
    }
};
