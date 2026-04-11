<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $table = 'inventory_transactions';

    protected $fillable = [
        'farm_id',
        'warehouse_id',
        'item_id',
        'flock_id',
        'transaction_date',
        'transaction_type',
        'direction',
        'source_module',
        'original_quantity',
        'computed_quantity',
        'unit_price',
        'total_amount',
        'payment_status',
        'supplier_name',
        'invoice_no',
        'invoice_attachment_path',
        'reference_no',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
        ];
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function flock(): BelongsTo
    {
        return $this->belongsTo(Flock::class, 'flock_id');
    }

    /** سجلات العلف المرتبطة بهذه الحركة */
    public function feedLogs(): HasMany
    {
        return $this->hasMany(FlockFeedLog::class, 'inventory_transaction_id');
    }

    /** سجلات الدواء المرتبطة بهذه الحركة */
    public function medicineLogs(): HasMany
    {
        return $this->hasMany(FlockMedicine::class, 'inventory_transaction_id');
    }

    /** المصروفات المرتبطة بهذه الحركة */
    public function linkedExpenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'linked_inventory_transaction_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
