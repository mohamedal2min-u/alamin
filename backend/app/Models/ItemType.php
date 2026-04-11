<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemType extends Model
{
    use HasFactory;

    protected $table = 'item_types';

    protected $fillable = [
        'farm_id',
        'name',
        'code',
        'is_system',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    /** null = نوع عام للنظام */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'item_type_id');
    }
}
