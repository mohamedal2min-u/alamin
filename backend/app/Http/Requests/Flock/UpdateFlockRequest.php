<?php

namespace App\Http\Requests\Flock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => ['sometimes', 'string', 'min:2', 'max:190'],
            'start_date'    => ['sometimes', 'date', 'date_format:Y-m-d'],
            'initial_count' => ['sometimes', 'integer', 'min:1'],
            'notes'         => ['nullable', 'string', 'max:5000'],
            'status'        => ['sometimes', Rule::in(['draft', 'active', 'closed', 'cancelled'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.min'               => 'اسم الفوج يجب أن يكون حرفين على الأقل',
            'name.max'               => 'اسم الفوج طويل جداً',
            'start_date.date'        => 'صيغة التاريخ غير صحيحة',
            'start_date.date_format' => 'صيغة التاريخ يجب أن تكون YYYY-MM-DD',
            'initial_count.integer'  => 'العدد الأولي يجب أن يكون رقماً صحيحاً',
            'initial_count.min'      => 'العدد الأولي يجب أن يكون أكبر من الصفر',
            'status.in'              => 'الحالة يجب أن تكون: draft أو active أو closed أو cancelled',
        ];
    }
}
