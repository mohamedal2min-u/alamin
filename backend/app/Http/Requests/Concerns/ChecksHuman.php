<?php

namespace App\Http\Requests\Concerns;

/**
 * تحقق تلقائي من أن المرسل إنسان، بدل سؤال الحساب في صفحة الدخول:
 *  - website: حقل مخفي عن الإنسان؛ البرامج الآلية تملؤه.
 *  - form_ms: الوقت منذ فتح النموذج حتى الإرسال؛ الإنسان يحتاج لحظات، البرنامج يرسل فوراً.
 * يعمل مع حدّ المحاولات (throttle) على المسار.
 */
trait ChecksHuman
{
    protected function humanRules(): array
    {
        return [
            'website' => ['prohibited'],
            'form_ms' => ['required', 'integer', 'min:800'],
        ];
    }

    protected function humanMessages(): array
    {
        return [
            'website.prohibited' => 'تعذّر التحقق، أعد المحاولة',
            'form_ms.required'   => 'تعذّر التحقق، حدّث الصفحة وأعد المحاولة',
            'form_ms.integer'    => 'تعذّر التحقق، حدّث الصفحة وأعد المحاولة',
            'form_ms.min'        => 'تعذّر التحقق، أعد المحاولة بعد لحظة',
        ];
    }
}
