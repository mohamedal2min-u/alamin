<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ربط صورة الفاتورة بقيدها (بيع / مصروف / ماء) لتظهر عند فتح القيد
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flock_documents', function (Blueprint $table) {
            $table->string('record_type', 20)->nullable()->after('flock_id');
            $table->unsignedBigInteger('record_id')->nullable()->after('record_type');
            $table->index(['record_type', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::table('flock_documents', function (Blueprint $table) {
            $table->dropIndex(['record_type', 'record_id']);
            $table->dropColumn(['record_type', 'record_id']);
        });
    }
};
