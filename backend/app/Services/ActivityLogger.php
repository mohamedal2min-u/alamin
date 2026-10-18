<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\FlockFeedLog;
use App\Models\FlockMedicine;
use App\Models\FlockMortality;
use App\Models\FlockNote;
use App\Models\FlockTemperatureLog;
use App\Models\FlockWaterLog;
use Illuminate\Database\Eloquent\Model;

/**
 * يكتب سجل النشاطات للإدخالات اليومية (إنشاء/تعديل/إلغاء) مع لقطة الحقول قبل وبعد.
 * الأسعار والدفعات لها سجلاتها الخاصة (PriceAuditLog / PaymentAuditLog) فلا تُكرَّر هنا.
 */
class ActivityLogger
{
    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const VOIDED = 'voided';

    /** نوع السجل في الواجهة/السجل ← الموديل */
    public const TYPES = [
        'mortality' => FlockMortality::class,
        'feed_log' => FlockFeedLog::class,
        'medicine_log' => FlockMedicine::class,
        'water_log' => FlockWaterLog::class,
        'note' => FlockNote::class,
        'temperature_log' => FlockTemperatureLog::class,
    ];

    /** الحقول التي تُحفظ في اللقطة لكل نوع */
    private const FIELDS = [
        'mortality' => ['entry_date', 'quantity', 'reason', 'notes'],
        'feed_log' => ['entry_date', 'item_id', 'quantity', 'unit_label', 'notes'],
        'medicine_log' => ['entry_date', 'item_id', 'quantity', 'unit_label', 'notes'],
        'water_log' => ['entry_date', 'quantity', 'unit_label', 'notes'],
        'note' => ['entry_date', 'note_type', 'note_text'],
        'temperature_log' => ['log_date', 'time_of_day', 'temperature', 'notes'],
    ];

    public static function typeOf(Model $model): ?string
    {
        $key = array_search($model::class, self::TYPES, true);

        return $key === false ? null : $key;
    }

    /** لقطة الحقول التشغيلية للسجل (التواريخ بصيغة Y-m-d، واسم الصنف للقراءة) */
    public function snapshot(Model $model): array
    {
        $type = self::typeOf($model);
        $out = [];
        foreach (self::FIELDS[$type] ?? [] as $field) {
            $value = $model->getAttribute($field);
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d');
            }
            if (in_array($field, ['quantity', 'temperature'], true) && $value !== null) {
                $value = (float) $value;
            }
            $out[$field] = $value;
        }
        if (in_array($type, ['feed_log', 'medicine_log'], true)) {
            $out['item_name'] = $model->item?->name;
        }

        return $out;
    }

    public function log(Model $subject, string $action, ?int $userId, ?array $before = null, ?array $after = null, ?string $reason = null): ActivityLog
    {
        return ActivityLog::create([
            'farm_id' => $subject->getAttribute('farm_id'),
            'user_id' => $userId,
            'flock_id' => $subject->getAttribute('flock_id'),
            'action' => $action,
            'subject_type' => self::typeOf($subject) ?? class_basename($subject),
            'subject_id' => $subject->getKey(),
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
        ]);
    }

    public function created(Model $subject, ?int $userId): void
    {
        try {
            $this->log($subject, self::CREATED, $userId, null, $this->snapshot($subject));
        } catch (\Throwable $e) {
            // السجل لا يعطّل الإدخال اليومي أبداً
            report($e);
        }
    }
}
