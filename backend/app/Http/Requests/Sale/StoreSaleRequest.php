<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sale_date'              => ['required', 'date_format:Y-m-d'],
            'buyer_name'             => ['nullable', 'string', 'max:190'],
            'reference_no'           => ['nullable', 'string', 'max:100'],
            'discount_amount'        => ['nullable', 'numeric', 'min:0'],
            'received_amount'        => ['nullable', 'numeric', 'min:0'],
            'notes'                  => ['nullable', 'string', 'max:5000'],

            'items'                  => ['required', 'array', 'min:1'],
            'items.*.birds_count'    => ['required', 'integer', 'min:1'],
            'items.*.total_weight_kg' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price_per_kg' => ['required', 'numeric', 'min:0.001'],
            'items.*.notes'          => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'sale_date.required'               => 'تاريخ البيع مطلوب',
            'sale_date.date_format'            => 'صيغة التاريخ يجب أن تكون YYYY-MM-DD',
            'items.required'                   => 'يجب إضافة سطر بيع واحد على الأقل',
            'items.min'                        => 'يجب إضافة سطر بيع واحد على الأقل',
            'items.*.birds_count.required'     => 'عدد الطيور مطلوب',
            'items.*.birds_count.min'          => 'عدد الطيور يجب أن يكون أكبر من صفر',
            'items.*.total_weight_kg.required' => 'الوزن الكلي مطلوب',
            'items.*.total_weight_kg.min'      => 'الوزن الكلي يجب أن يكون أكبر من صفر',
            'items.*.unit_price_per_kg.required' => 'سعر الكيلو مطلوب',
            'items.*.unit_price_per_kg.min'    => 'سعر الكيلو يجب أن يكون أكبر من صفر',
        ];
    }
}
