<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_partner_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->restrictOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->restrictOnDelete();
            $table->decimal('share_percent', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['farm_id', 'is_active'], 'idx_partner_shares_farm');
        });

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE farm_partner_shares
            ADD CONSTRAINT chk_share_percent_range
            CHECK (share_percent >= 0 AND share_percent <= 100)
        ");
        }

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            DB::statement("
            ALTER TABLE farm_partner_shares
            ADD CONSTRAINT chk_share_effective_dates
            CHECK (effective_to IS NULL OR effective_to >= effective_from)
        ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_partner_shares');
    }
};
