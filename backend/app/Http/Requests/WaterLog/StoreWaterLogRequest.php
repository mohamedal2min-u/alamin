<?php

namespace App\Http\Requests\WaterLog;

use Illuminate\Foundation\Http\FormRequest;

class StoreWaterLogRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'quantity'   => ['nullable', 'numeric', 'min:0.001'],
            'entry_date' => ['nullable', 'date_format:Y-m-d'],
            'unit_label' => ['nullable', 'string', 'max:50'],
            'notes'      => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.min'       => 'الكمية يجب أن تكون أكبر من صفر',
            'entry_date.date_format' => 'صيغة التاريخ غير صحيحة',
        ];
    }
}
