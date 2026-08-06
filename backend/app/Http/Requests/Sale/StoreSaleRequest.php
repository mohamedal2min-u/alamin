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
            'vehicle_weight_before_kg' => ['nullable', 'numeric', 'min:0'],
            'vehicle_weight_after_kg' => [
                'nullable', 'numeric', 'min:0',
                function ($attribute, $value, $fail) {
                    $before = $this->input('vehicle_weight_before_kg');
                    if ($before !== null && $value <= $before) {
                        $fail('الوزن الثاني يجب أن يكون أكبر من الوزن الأول');
                    }
                },
            ],
            'weight_deduction_kg'    => ['nullable', 'numeric', 'min:0'],
            'discount_amount'        => ['nullable', 'numeric', 'min:0'],
            'received_amount'        => ['nullable', 'numeric', 'min:0'],
            'notes'                  => ['nullable', 'string', 'max:5000'],

            'items'                  => ['required', 'array', 'min:1'],
            'items.*.birds_count'    => ['required', 'integer', 'min:1'],
            'items.*.total_weight_kg' => ['required', 'numeric', 'min:0.001'],
            'items.*.crates_count'   => ['nullable', 'integer', 'min:0'],
            'items.*.crate_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'items.*.gross_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price_per_kg' => ['nullable', 'numeric', 'min:0.001'],
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
            'items.*.total_weight_kg.required' => 'الوزن الصافي مطلوب',
            'items.*.total_weight_kg.min'      => 'الوزن الصافي يجب أن يكون أكبر من صفر',
            'items.*.crates_count.min'         => 'عدد الأقفاص لا يمكن أن يكون سالباً',
            'items.*.crate_weight_kg.min'      => 'وزن القفص لا يمكن أن يكون سالباً',
            'items.*.gross_weight_kg.min'      => 'الوزن القائم لا يمكن أن يكون سالباً',
            'items.*.unit_price_per_kg.min'    => 'سعر الكيلو يجب أن يكون أكبر من صفر',
        ];
    }
}
