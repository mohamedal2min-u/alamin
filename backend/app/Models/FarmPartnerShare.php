<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmPartnerShare extends Model
{
    use HasFactory;

    protected $table = 'farm_partner_shares';

    protected $fillable = [
        'farm_id',
        'partner_id',
        'share_percent',
        'is_active',
        'effective_from',
        'effective_to',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'effective_from' => 'date',
            'effective_to'   => 'date',
        ];
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
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
