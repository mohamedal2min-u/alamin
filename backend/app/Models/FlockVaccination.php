<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlockVaccination extends Model
{
    public const STATUSES = ['due', 'done', 'skipped'];

    protected $fillable = [
        'farm_id', 'flock_id', 'age_day', 'due_date', 'title', 'method', 'notes',
        'status', 'done_at', 'done_by', 'note', 'withdrawal_days',
    ];

    protected function casts(): array
    {
        return [
            'due_date'        => 'date',
            'done_at'         => 'datetime',
            'age_day'         => 'integer',
            'withdrawal_days' => 'integer',
        ];
    }

    public function flock(): BelongsTo
    {
        return $this->belongsTo(Flock::class, 'flock_id');
    }

    public function doneByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }
}
