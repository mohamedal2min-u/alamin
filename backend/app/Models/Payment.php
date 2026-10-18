<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * دفعة واحدة مؤرَّخة على مصروف أو بيع أو سجل ماء.
 * مجموع دفعات السجل = paid_amount (أو received_amount للبيع).
 */
class Payment extends Model
{
    public const METHODS = ['cash', 'transfer', 'other'];

    public const PAYABLE_TYPES = ['expense', 'sale', 'water_log'];

    protected $fillable = [
        'farm_id',
        'contact_id',
        'payable_type',
        'payable_id',
        'amount',
        'paid_at',
        'method',
        'note',
        'is_opening',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'date',
            'amount' => 'float',
            'is_opening' => 'boolean',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
