<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlockFeedLog extends Model
{
    use HasFactory;

    protected $table = 'flock_feed_logs';

    protected $fillable = [
        'farm_id',
        'flock_id',
        'item_id',
        'entry_date',
        'quantity',
        'unit_label',
        'notes',
        'worker_id',
        'inventory_transaction_id',
        'editable_until',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date'    => 'date',
            'editable_until' => 'datetime',
        ];
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    public function flock(): BelongsTo
    {
        return $this->belongsTo(Flock::class, 'flock_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
    }

    public function inventoryTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'inventory_transaction_id');
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
