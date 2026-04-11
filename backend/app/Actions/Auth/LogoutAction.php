<?php

namespace App\Actions\Auth;

use App\Models\User;

class LogoutAction
{
    /**
     * يحذف الـ token الحالي فقط (لا يحذف باقي الـ tokens الخاصة بالمستخدم).
     */
    public function execute(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
