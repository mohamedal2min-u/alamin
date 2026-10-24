<?php

namespace App\Http\Requests\Contact;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim(preg_replace('/\s+/u', ' ', $this->input('name')))]);
        }
    }

    public function rules(): array
    {
        $farmId = (int) $this->attributes->get('farm_id');
        $contactId = $this->route('contact');
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');

        return [
            'name' => [
                $isUpdate ? 'sometimes' : 'required', 'string', 'min:2', 'max:190',
                Rule::unique('contacts', 'name')->where('farm_id', $farmId)->ignore($contactId),
            ],
            'type' => [$isUpdate ? 'sometimes' : 'required', Rule::in(Contact::TYPES)],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'الاسم مطلوب',
            'name.min' => 'الاسم قصير جداً',
            'name.unique' => 'يوجد مورد/تاجر بهذا الاسم في المزرعة',
            'type.required' => 'النوع مطلوب',
            'type.in' => 'النوع يجب أن يكون مورداً أو تاجراً أو كليهما',
        ];
    }
}
