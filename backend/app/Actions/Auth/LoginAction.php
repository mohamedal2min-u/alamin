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
        $loginRaw = $data['login'];
        $loginNormalized = $loginRaw;

        // Normalize Saudi numbers: 05... -> 9665..., +966... -> 966...
        if (preg_match('/^05[0-9]{8}$/', $loginRaw)) {
            $loginNormalized = '966' . ltrim($loginRaw, '0');
        } elseif (preg_match('/^\+966[0-9]{9}$/', $loginRaw)) {
            $loginNormalized = ltrim($loginRaw, '+');
        }

        // ── البحث عن المستخدم بالبريد الإلكتروني أو الواتساب ─────────────────
        $user = User::where(function ($query) use ($loginRaw, $loginNormalized) {
            $query->where('email', $loginRaw)
                  ->orWhere('whatsapp', $loginRaw)
                  ->orWhere('whatsapp', $loginNormalized);
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
