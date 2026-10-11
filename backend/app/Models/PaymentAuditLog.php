<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * سجل تعديل المدفوعات. للإضافة فقط: أي محاولة تعديل أو حذف تُرفض.
 */
class PaymentAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['farm_id', 'user_id', 'record_type', 'record_id', 'old_amount', 'new_amount'];

    protected function casts(): array
    {
        return [
            'old_amount' => 'float',
            'new_amount' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('سجل المدفوعات لا يمكن تعديله'));
        static::deleting(fn () => throw new LogicException('سجل المدفوعات لا يمكن حذفه'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
