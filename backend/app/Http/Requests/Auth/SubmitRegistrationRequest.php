<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SubmitRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:150'],
            'whatsapp'  => ['required', 'string', 'max:30'],
            'password'  => ['required', 'string', 'min:8', 'confirmed'],
            'email'     => ['nullable', 'email', 'max:190'],
            'location'  => ['nullable', 'string', 'max:255'],
            'farm_name' => ['nullable', 'string', 'max:190'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'         => 'الاسم مطلوب',
            'whatsapp.required'     => 'رقم الواتساب مطلوب',
            'password.required'     => 'كلمة المرور مطلوبة',
            'password.min'          => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
            'password.confirmed'    => 'تأكيد كلمة المرور غير متطابق',
            'email.email'           => 'صيغة البريد الإلكتروني غير صحيحة',
        ];
    }
}
