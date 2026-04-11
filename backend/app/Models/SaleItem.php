<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $table = 'sale_items';

    protected $fillable = [
        'sale_id',
        'farm_id',
        'flock_id',
        'birds_count',
        'total_weight_kg',
        'avg_weight_kg',
        'unit_price_per_kg',
        'line_total',
        'notes',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    public function flock(): BelongsTo
    {
        return $this->belongsTo(Flock::class, 'flock_id');
    }
}
