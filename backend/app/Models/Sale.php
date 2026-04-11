<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'farm_id',
        'flock_id',
        'sale_date',
        'reference_no',
        'buyer_name',
        'invoice_attachment_path',
        'gross_amount',
        'discount_amount',
        'net_amount',
        'received_amount',
        'remaining_amount',
        'payment_status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
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

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
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
