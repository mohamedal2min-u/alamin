<?php

namespace App\Actions\Auth;

use App\Models\User;

class ChangePasswordAction
{
    /**
     * @param  array{password: string}  $data  (current_password مُتحقَّق منه في FormRequest)
     */
    public function execute(User $user, array $data): void
    {
        $user->update(['password' => $data['password']]);

        // إلغاء جميع الـ tokens الأخرى (تسجيل خروج من الأجهزة الأخرى)
        $currentToken = $user->currentAccessToken()->id ?? null;

        $user->tokens()
            ->when($currentToken, fn ($q) => $q->where('id', '!=', $currentToken))
            ->delete();
    }
}
