<?php

namespace App\Http\Requests\FeedLog;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeedLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $farmId = $this->attributes->get('farm_id');

        return [
            'item_id'    => ['required', 'integer', "exists:items,id,farm_id,{$farmId},status,active"],
            'quantity'   => ['required', 'numeric', 'min:0.001'],
            'entry_date' => ['nullable', 'date_format:Y-m-d'],
            'unit_label' => ['nullable', 'string', 'max:50'],
            'notes'      => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'item_id.required'  => 'يجب اختيار صنف العلف',
            'item_id.exists'    => 'الصنف غير موجود في مخزون هذه المزرعة',
            'quantity.required' => 'الكمية مطلوبة',
            'quantity.min'      => 'الكمية يجب أن تكون أكبر من صفر',
        ];
    }
}
