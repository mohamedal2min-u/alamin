<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\ChecksHuman;
use Illuminate\Foundation\Http\FormRequest;

class SubmitRegistrationRequest extends FormRequest
{
    use ChecksHuman;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->humanRules(),
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
            ...$this->humanMessages(),
            'name.required'         => 'الاسم مطلوب',
            'whatsapp.required'     => 'رقم الواتساب مطلوب',
            'password.required'     => 'كلمة المرور مطلوبة',
            'password.min'          => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
            'password.confirmed'    => 'تأكيد كلمة المرور غير متطابق',
            'email.email'           => 'صيغة البريد الإلكتروني غير صحيحة',
        ];
    }
}
