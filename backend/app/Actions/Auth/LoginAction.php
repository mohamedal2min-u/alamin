<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginAction
{
    public function __construct(
        private readonly BuildUserDataAction $buildUserData,
    ) {}

    /**
     * @param  array{login: string, password: string}  $data
     * @return array{token: string, user: array}
     *
     * @throws \Exception
     */
    public function execute(array $data): array
    {
        // ── البحث عن المستخدم بالبريد الإلكتروني أو الواتساب ─────────────────
        $user = User::where(function ($query) use ($data) {
            $query->where('email', $data['login'])
                  ->orWhere('whatsapp', $data['login']);
        })->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw new \Exception('بيانات الدخول غير صحيحة', 401);
        }

        if ($user->status !== 'active') {
            $messages = [
                'inactive'  => 'هذا الحساب غير نشط',
                'suspended' => 'هذا الحساب موقوف، يرجى التواصل مع المسؤول',
            ];
            throw new \Exception($messages[$user->status] ?? 'هذا الحساب غير مفعّل', 403);
        }

        // ── تحديث وقت آخر تسجيل دخول ────────────────────────────────────────
        $user->update(['last_login_at' => now()]);

        // ── إنشاء Sanctum token ───────────────────────────────────────────────
        $token = $user->createToken('api')->plainTextToken;

        return [
            'token' => $token,
            'user'  => $this->buildUserData->execute($user),
        ];
    }
}
