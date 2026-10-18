<?php

namespace App\Models\Concerns;

use App\Services\PaymentLedgerService;
use Illuminate\Database\Eloquent\Model;

/**
 * سجل مالي له دفعات (مصروف/بيع/ماء): أي مبلغ مدفوع عند الإنشاء يُسجَّل
 * فوراً كدفعة بتاريخ السجل، فيبقى مجموع الدفعات مطابقاً للمدفوع دائماً.
 */
trait IsPayable
{
    public static function bootIsPayable(): void
    {
        static::created(function (Model $model): void {
            app(PaymentLedgerService::class)->sync(
                $model,
                PaymentLedgerService::dateOf($model),
                'cash',
                'دفعة عند الإدخال',
                $model->created_by ?? null,
            );
        });
    }
}
