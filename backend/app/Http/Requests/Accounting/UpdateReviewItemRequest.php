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
            // ربط سجل قديم بمورد/تاجر من المزرعة نفسها
            'contact_id' => ['sometimes', 'nullable', 'integer', \App\Http\Requests\Contact\ContactRule::exists($this)],
        ];

        if ($type === 'expense' || $type === 'water_log' || $type === 'sale') {
            // لا يُسمح بمسح السعر (null)؛ يمكن فقط تغييره، وكل تغيير يُسجَّل
            $rules['unit_price'] = ['sometimes', 'required', 'numeric', 'min:0'];
        }

        return $rules;
    }
}
