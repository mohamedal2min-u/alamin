<?php

namespace App\Http\Requests\Contact;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payable_type' => ['required', Rule::in(Payment::PAYABLE_TYPES)],
            'payable_id' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'paid_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'مبلغ الدفعة مطلوب',
            'amount.gt' => 'مبلغ الدفعة يجب أن يكون أكبر من صفر',
            'paid_at.required' => 'تاريخ الدفعة مطلوب',
            'paid_at.before_or_equal' => 'تاريخ الدفعة لا يمكن أن يكون في المستقبل',
            'method.in' => 'طريقة الدفع غير صالحة',
        ];
    }
}
