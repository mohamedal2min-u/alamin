<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    use HasFactory;

    protected $fillable = [
        'farm_id',
        'user_id',
        'name',
        'email',
        'whatsapp',
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

    /** حساب المستخدم في النظام — قد يكون null */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function farmPartnerShares(): HasMany
    {
        return $this->hasMany(FarmPartnerShare::class, 'partner_id');
    }

    public function partnerTransactions(): HasMany
    {
        return $this->hasMany(PartnerTransaction::class, 'partner_id');
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
