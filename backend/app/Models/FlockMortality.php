<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlockMortality extends Model
{
    use HasFactory;

    protected $table = 'flock_mortalities';

    protected $fillable = [
        'farm_id',
        'flock_id',
        'entry_date',
        'quantity',
        'reason',
        'notes',
        'worker_id',
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

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
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
