<?php

namespace App\Actions\Auth;

use App\Models\User;

class UpdateProfileAction
{
    /**
     * يحدّث الحقول المُمرَّرة فقط (بما فيها القيم null للحقول الاختيارية).
     * FormRequest تستخدم 'sometimes' فلا يصل هنا إلا ما أرسله المستخدم فعلاً.
     *
     * @param  array{name?: string, email?: string|null, whatsapp?: string|null, avatar_path?: string|null}  $data
     */
    public function execute(User $user, array $data): User
    {
        $user->fill($data)->save();

        return $user->refresh();
    }
}
