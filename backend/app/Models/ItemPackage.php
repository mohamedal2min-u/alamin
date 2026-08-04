<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemPackage extends Model
{
    protected $fillable = [
        'farm_id',
        'item_id',
        'label',
        'quantity',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
