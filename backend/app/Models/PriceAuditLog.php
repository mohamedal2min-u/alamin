<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * سجل تعديل الأسعار. للإضافة فقط: أي محاولة تعديل أو حذف تُرفض.
 */
class PriceAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['farm_id', 'user_id', 'record_type', 'record_id', 'old_price', 'new_price'];

    protected function casts(): array
    {
        return [
            'old_price' => 'float',
            'new_price' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('سجل الأسعار لا يمكن تعديله'));
        static::deleting(fn () => throw new LogicException('سجل الأسعار لا يمكن حذفه'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
