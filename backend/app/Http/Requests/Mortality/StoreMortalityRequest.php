<?php
// backend/app/Http/Requests/Mortality/StoreMortalityRequest.php

namespace App\Http\Requests\Mortality;

use Illuminate\Foundation\Http\FormRequest;

class StoreMortalityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date', 'date_format:Y-m-d'],
            'quantity'   => ['required', 'integer', 'min:1'],
            'reason'     => ['nullable', 'string', 'max:190'],
            'notes'      => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'entry_date.required'    => 'تاريخ الإدخال مطلوب',
            'entry_date.date_format' => 'صيغة التاريخ يجب أن تكون YYYY-MM-DD',
            'quantity.required'      => 'الكمية مطلوبة',
            'quantity.integer'       => 'الكمية يجب أن تكون عدداً صحيحاً',
            'quantity.min'           => 'الكمية يجب أن تكون أكبر من صفر',
        ];
    }
}
