<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\ChecksHuman;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            // يقبل بريد إلكتروني أو رقم واتساب
            'login'    => ['required', 'string', 'max:190'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->humanMessages(),
            'login.required'    => 'البريد الإلكتروني أو رقم الواتساب مطلوب',
            'password.required' => 'كلمة المرور مطلوبة',
        ];
    }
}
