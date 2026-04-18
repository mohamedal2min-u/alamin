<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->route('type');

        $rules = [
            'paid_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];

        if ($type === 'expense') {
            $rules['unit_price'] = ['sometimes', 'nullable', 'numeric', 'min:0'];
        }

        return $rules;
    }
}
