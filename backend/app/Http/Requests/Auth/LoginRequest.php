<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // يقبل بريد إلكتروني أو رقم واتساب
            'login'    => ['required', 'string', 'max:190'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required'    => 'البريد الإلكتروني أو رقم الواتساب مطلوب',
            'password.required' => 'كلمة المرور مطلوبة',
        ];
    }
}
