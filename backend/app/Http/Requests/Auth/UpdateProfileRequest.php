<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name'        => ['sometimes', 'required', 'string', 'max:150'],
            'email'       => ['sometimes', 'nullable', 'email', 'max:190', Rule::unique('users')->ignore($userId)],
            'whatsapp'    => ['sometimes', 'nullable', 'string', 'max:30', Rule::unique('users')->ignore($userId)],
            'avatar_path' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'الاسم مطلوب',
            'email.email'        => 'صيغة البريد الإلكتروني غير صحيحة',
            'email.unique'       => 'البريد الإلكتروني مستخدم مسبقاً',
            'whatsapp.unique'    => 'رقم الواتساب مستخدم مسبقاً',
        ];
    }
}
