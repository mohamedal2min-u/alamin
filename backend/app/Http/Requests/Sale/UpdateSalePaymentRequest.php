<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'received_amount' => ['required', 'numeric', 'min:0'],
            'notes'           => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'received_amount.required' => 'المبلغ المستلم مطلوب',
            'received_amount.min'      => 'المبلغ المستلم لا يمكن أن يكون سالبًا',
        ];
    }
}
