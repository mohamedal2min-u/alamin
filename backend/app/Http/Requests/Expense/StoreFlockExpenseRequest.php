<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;

class StoreFlockExpenseRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'expense_type' => ['required', 'string', 'in:water,bedding,other'],
            'quantity'     => ['nullable', 'numeric', 'min:0'],
            'unit_price'   => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'description'  => ['nullable', 'string', 'max:255'],
            'notes'        => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'expense_type.required' => 'نوع المصروف مطلوب',
            'expense_type.in'       => 'نوع المصروف يجب أن يكون: مياه أو فرشة أو أخرى',
            'total_amount.required' => 'المبلغ الإجمالي مطلوب',
            'total_amount.min'      => 'المبلغ يجب أن يكون أكبر من أو يساوي صفر',
        ];
    }
}
