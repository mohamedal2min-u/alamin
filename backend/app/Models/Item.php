<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'farm_id',
        'item_type_id',
        'name',
        'input_unit',
        'unit_value',
        'content_unit',
        'minimum_stock',
        'default_cost',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ItemType::class, 'item_type_id');
    }

    public function warehouseItems(): HasMany
    {
        return $this->hasMany(WarehouseItem::class, 'item_id');
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'item_id');
    }

    public function feedLogs(): HasMany
    {
        return $this->hasMany(FlockFeedLog::class, 'item_id');
    }

    public function medicines(): HasMany
    {
        return $this->hasMany(FlockMedicine::class, 'item_id');
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
