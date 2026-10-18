<?php

namespace App\Models\Concerns;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ربط اختياري بمورد/تاجر. عند اختيار جهة يُنسخ اسمها إلى الحقل النصي القديم
 * (buyer_name / supplier_name) إن وُجد، كي تبقى الشاشات والتقارير القديمة صحيحة.
 * الموديل يحدد الحقل عبر الخاصية $contactNameColumn.
 */
trait HasContact
{
    public static function bootHasContact(): void
    {
        static::saving(function (Model $model): void {
            $column = property_exists($model, 'contactNameColumn') ? $model->contactNameColumn : null;
            if ($column && $model->contact_id && $model->isDirty('contact_id')) {
                $name = Contact::whereKey($model->contact_id)->value('name');
                if ($name !== null) {
                    $model->{$column} = $name;
                }
            }
        });
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}
