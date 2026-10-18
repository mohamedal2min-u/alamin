<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** سجل نشاطات الإدخالات اليومية — للإضافة فقط، لا يُعدَّل ولا يُحذف */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'farm_id',
        'user_id',
        'flock_id',
        'action',
        'subject_type',
        'subject_id',
        'before',
        'after',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function flock(): BelongsTo
    {
        return $this->belongsTo(Flock::class, 'flock_id');
    }
}
