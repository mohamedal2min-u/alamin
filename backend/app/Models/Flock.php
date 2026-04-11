<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Flock extends Model
{
    use HasFactory;

    protected $fillable = [
        'farm_id',
        'name',
        'status',
        'start_date',
        'close_date',
        'initial_count',
        'current_age_days',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'close_date' => 'date',
        ];
    }

    // ─── Farm ─────────────────────────────────────────────────────────────────

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    // ─── Logs ─────────────────────────────────────────────────────────────────

    public function mortalities(): HasMany
    {
        return $this->hasMany(FlockMortality::class, 'flock_id');
    }

    public function feedLogs(): HasMany
    {
        return $this->hasMany(FlockFeedLog::class, 'flock_id');
    }

    public function medicines(): HasMany
    {
        return $this->hasMany(FlockMedicine::class, 'flock_id');
    }

    public function waterLogs(): HasMany
    {
        return $this->hasMany(FlockWaterLog::class, 'flock_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(FlockNote::class, 'flock_id');
    }

    // ─── Accounting ───────────────────────────────────────────────────────────

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'flock_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'flock_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'flock_id');
    }

    // ─── Audit ────────────────────────────────────────────────────────────────

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
