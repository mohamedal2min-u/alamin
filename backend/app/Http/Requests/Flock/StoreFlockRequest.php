<?php

namespace App\Http\Requests\Flock;

use Illuminate\Foundation\Http\FormRequest;

class StoreFlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحية تتحكم بها middleware
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'min:2', 'max:190'],
            'start_date'    => ['required', 'date', 'date_format:Y-m-d'],
            'initial_count'    => ['required', 'integer', 'min:1'],
            'chick_unit_price' => ['nullable', 'numeric', 'min:0'],
            'notes'            => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'          => 'اسم الفوج مطلوب',
            'name.min'               => 'اسم الفوج يجب أن يكون حرفين على الأقل',
            'name.max'               => 'اسم الفوج طويل جداً',
            'start_date.required'    => 'تاريخ بدء الفوج مطلوب',
            'start_date.date'        => 'صيغة التاريخ غير صحيحة',
            'start_date.date_format' => 'صيغة التاريخ يجب أن تكون YYYY-MM-DD',
            'initial_count.required' => 'العدد الأولي مطلوب',
            'initial_count.integer'  => 'العدد الأولي يجب أن يكون رقماً صحيحاً',
            'initial_count.min'      => 'العدد الأولي يجب أن يكون أكبر من الصفر',
            'chick_unit_price.numeric' => 'سعر الصوص يجب أن يكون رقماً',
            'chick_unit_price.min'     => 'سعر الصوص لا يمكن أن يكون أقل من الصفر',
        ];
    }
}
