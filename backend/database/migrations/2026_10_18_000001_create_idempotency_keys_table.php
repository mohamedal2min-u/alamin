<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // مفاتيح منع التكرار للإدخال اليومي (إعادة الإرسال من صندوق الانتظار عند ضعف الشبكة)
        // يُحفظ الرد الناجح 48 ساعة ويُعاد كما هو لنفس المفتاح بدل إنشاء سجل مكرر
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('key');
            $table->string('route', 191);                                // METHOD + المسار الفعلي
            $table->char('request_hash', 64);                            // sha256 لمحتوى الطلب
            $table->unsignedSmallInteger('response_status')->nullable(); // null = قيد التنفيذ
            $table->longText('response_body')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // الفهرس الفريد يغطي farm_id أيضاً
            $table->unique(['farm_id', 'user_id', 'key']);
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
