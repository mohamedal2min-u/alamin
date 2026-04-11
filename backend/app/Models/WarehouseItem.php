<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseItem extends Model
{
    protected $table = 'warehouse_items';

    protected $fillable = [
        'farm_id',
        'warehouse_id',
        'item_id',
        'current_quantity',
        'average_cost',
        'last_in_at',
        'last_out_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'last_in_at'  => 'datetime',
            'last_out_at' => 'datetime',
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
}
