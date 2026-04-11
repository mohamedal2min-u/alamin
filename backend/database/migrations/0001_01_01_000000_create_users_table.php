<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 190)->nullable()->unique();
            $table->string('whatsapp', 30)->nullable()->unique();
            $table->string('password');
            $table->string('status', 30)->default('active');
            $table->string('avatar_path')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampTz('email_verified_at')->nullable();
            $table->rememberToken();
            // self-referential audit columns — no FK constraint في هذه المرحلة
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestampsTz();

            $table->index('status', 'idx_users_status');
        });

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE users
            ADD CONSTRAINT chk_users_status
            CHECK (status IN ('active', 'inactive', 'suspended'))
        ");
        }

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
