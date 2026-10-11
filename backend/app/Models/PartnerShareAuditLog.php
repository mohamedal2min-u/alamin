<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * سجل تعديل حصص الشركاء. للإضافة فقط: أي محاولة تعديل أو حذف تُرفض.
 */
class PartnerShareAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['farm_id', 'partner_id', 'user_id', 'old_percent', 'new_percent'];

    protected function casts(): array
    {
        return ['old_percent' => 'float', 'new_percent' => 'float'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('سجل الحصص لا يمكن تعديله'));
        static::deleting(fn () => throw new LogicException('سجل الحصص لا يمكن حذفه'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
