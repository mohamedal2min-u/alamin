<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlockTemperatureLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'farm_id',
        'flock_id',
        'log_date',
        'time_of_day',
        'temperature',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'log_date' => 'date',
        'temperature' => 'decimal:2',
    ];

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function flock(): BelongsTo
    {
        return $this->belongsTo(Flock::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
