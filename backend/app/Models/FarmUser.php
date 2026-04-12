<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class FarmUser extends Pivot
{
    use HasFactory;
    protected $table = 'farm_users';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'farm_id',
        'user_id',
        'status',
        'is_primary',
        'joined_at',
        'salary',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'joined_at'  => 'datetime',
            'salary'     => 'decimal:2',
        ];
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
