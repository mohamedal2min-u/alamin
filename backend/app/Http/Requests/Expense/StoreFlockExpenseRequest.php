<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;

class StoreFlockExpenseRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'expense_type' => ['required', 'string', 'in:water,bedding,farm_wash,disinfectant,other'],
            'quantity'     => ['required', 'numeric', 'min:0.001'],
            'unit_price'   => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'entry_date'   => ['nullable', 'date_format:Y-m-d'],
            'description'  => ['nullable', 'string', 'max:255'],
            'notes'        => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'expense_type.required' => 'نوع المصروف مطلوب',
            'expense_type.in'       => 'نوع المصروف غير صالح',
            'quantity.required'     => 'العدد مطلوب',
            'quantity.min'          => 'العدد يجب أن يكون أكبر من صفر',
        ];
    }
}
