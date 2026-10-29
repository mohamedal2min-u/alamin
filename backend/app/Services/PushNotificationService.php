<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * الدفع عبر Firebase (FCM HTTP v1) لتطبيق الأندرويد.
 * أولوية عالية: يصل فوراً والهاتف بوضع الخمول (Doze)، وينتظر الهاتف المطفأ حتى 4 أسابيع.
 * الإشعار يبقى في شريط الإشعارات حتى يضغط عليه المستخدم.
 */
class PushNotificationService
{
    private const FALLBACK_PROJECT_ID = 'alamin-1e1ca';

    /** تبقى الرسالة عند Firebase حتى يتصل الهاتف (الحد الأقصى المسموح) */
    private const TTL = '2419200s';

    /**
     * Send a push notification to specific users.
     *
     * @param  \Illuminate\Support\Collection|array|User  $users
     */
    public function sendNotification($users, string $title, string $body, array $data = [], string $channel = 'default', string $sound = 'default'): void
    {
        if ($users instanceof User) {
            $users = collect([$users]);
        } elseif (is_array($users)) {
            $users = collect($users);
        }

        foreach ($users as $user) {
            if ($user->fcm_token) {
                $this->sendToUser($user, $title, $body, $data, $channel, $sound);
            }
        }
    }

    /**
     * يرسل لجهاز المستخدم ويعيد النتيجة (للفحص). الرمز الملغى (UNREGISTERED) يُمسح من الحساب.
     *
     * @return array{ok: bool, status: ?int, error: ?string}
     */
    public function sendToUser(User $user, string $title, string $body, array $data = [], string $channel = 'default', string $sound = 'default'): array
    {
        $token = $user->fcm_token;
        if (! $token) {
            return ['ok' => false, 'status' => null, 'error' => 'لا يوجد رمز جهاز (fcm_token) لهذا المستخدم'];
        }

        try {
            $response = Http::withToken($this->getAccessToken())
                ->timeout(15)
                ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId()}/messages:send", [
                    'message' => $this->message($token, $title, $body, $data, $channel, $sound),
                ]);

            if ($response->successful()) {
                return ['ok' => true, 'status' => $response->status(), 'error' => null];
            }

            $error = $response->json('error.details.0.errorCode') ?? $response->json('error.status') ?? 'UNKNOWN';
            Log::error("FCM Send Error (user {$user->id}): ".$response->body());
            if ($error === 'UNREGISTERED') {
                // التطبيق حُذف أو الرمز تغيّر؛ يُسجَّل رمز جديد عند فتح التطبيق
                $user->forceFill(['fcm_token' => null])->saveQuietly();
            }

            return ['ok' => false, 'status' => $response->status(), 'error' => $error.': '.mb_substr($response->body(), 0, 300)];
        } catch (\Throwable $e) {
            Log::error('Push Notification Exception: '.$e->getMessage());

            return ['ok' => false, 'status' => null, 'error' => $e->getMessage()];
        }
    }

    public function message(string $token, string $title, string $body, array $data, string $channel, string $sound): array
    {
        return [
            'token' => $token,
            'notification' => ['title' => $title, 'body' => $body],
            'data' => array_map('strval', $data), // FCM data values must be strings
            'android' => [
                'priority' => 'high',
                'ttl' => self::TTL,
                'notification' => [
                    'sound' => $sound,
                    'channel_id' => $channel,
                    'notification_priority' => 'PRIORITY_MAX',
                    'visibility' => 'PUBLIC',
                    'default_vibrate_timings' => true,
                ],
            ],
        ];
    }

    public function serviceAccountPath(): string
    {
        return storage_path('app/firebase-service-account.json');
    }

    public function projectId(): string
    {
        $path = $this->serviceAccountPath();
        if (is_readable($path)) {
            $id = json_decode((string) file_get_contents($path), true)['project_id'] ?? null;
            if ($id) {
                return $id;
            }
        }

        return self::FALLBACK_PROJECT_ID;
    }

    /**
     * Get OAuth2 Access Token for Firebase
     */
    protected function getAccessToken(): string
    {
        return cache()->remember('fcm_access_token', 3500, function () {
            $client = new \Google\Client();
            $client->setAuthConfig($this->serviceAccountPath());
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $client->refreshTokenWithAssertion();
            $token = $client->getAccessToken();

            return $token['access_token'];
        });
    }
}
