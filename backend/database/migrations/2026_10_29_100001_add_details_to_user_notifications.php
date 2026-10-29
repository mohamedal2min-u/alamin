<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // تفاصيل التعديل (حقول قبل/بعد) تُعرض عند الضغط على الإشعار، ورابط السجل، ووقت القراءة
        Schema::table('user_notifications', function (Blueprint $table): void {
            $table->json('details')->nullable()->after('body');
            $table->string('link', 200)->nullable()->after('details');
            $table->timestamp('read_at')->nullable()->after('link');
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table): void {
            $table->dropColumn(['details', 'link', 'read_at']);
        });
    }
};
