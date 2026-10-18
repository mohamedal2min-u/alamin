<?php

namespace App\Http\Requests\Vaccination;

use App\Models\VaccineScheduleTemplateItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVaccineTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                    => ['required', 'string', 'min:2', 'max:150'],
            'items'                   => ['present', 'array', 'max:60'],
            'items.*.age_day'         => ['required', 'integer', 'min:1', 'max:120'],
            'items.*.title'           => ['required', 'string', 'min:2', 'max:150'],
            'items.*.method'          => ['required', Rule::in(VaccineScheduleTemplateItem::METHODS)],
            'items.*.notes'           => ['nullable', 'string', 'max:1000'],
            'items.*.withdrawal_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'             => 'اسم البرنامج مطلوب',
            'items.*.age_day.required'  => 'يوم العمر مطلوب',
            'items.*.age_day.min'       => 'يوم العمر يبدأ من 1',
            'items.*.age_day.max'       => 'يوم العمر كبير جداً',
            'items.*.title.required'    => 'اسم اللقاح مطلوب',
            'items.*.method.in'         => 'طريقة الإعطاء غير صحيحة',
            'items.*.withdrawal_days.*' => 'فترة السحب يجب أن تكون عدد أيام صحيحاً',
        ];
    }
}
