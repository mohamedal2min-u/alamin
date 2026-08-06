<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // وزن قبان السيارة الإلكتروني — قبل التحميل وبعده، مطابقة لوصل القبان الورقي.
            $table->decimal('vehicle_weight_before_kg', 10, 3)->nullable()->after('buyer_name');
            $table->decimal('vehicle_weight_after_kg', 10, 3)->nullable()->after('vehicle_weight_before_kg');
            // خصم يدوي إضافي من الوزن الصافي (فاقد ماء، اتفاق ودي...).
            $table->decimal('weight_deduction_kg', 10, 3)->nullable()->after('vehicle_weight_after_kg');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['vehicle_weight_before_kg', 'vehicle_weight_after_kg', 'weight_deduction_kg']);
        });
    }
};
