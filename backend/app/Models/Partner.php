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

    /**
     * The farm this partner belongs to.
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * The system user associated with this partner (if any).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The shares this partner holds in various farms/flocks.
     */
    public function shares(): HasMany
    {
        return $this->hasMany(FarmPartnerShare::class);
    }

    /**
     * The financial transactions associated with this partner.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(PartnerTransaction::class);
    }
}
