<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    /**
     * Send a push notification to specific users.
     *
     * @param  \Illuminate\Support\Collection|array|User  $users
     */
    public function sendNotification($users, string $title, string $body, array $data = []): void
    {
        if ($users instanceof User) {
            $users = collect([$users]);
        } elseif (is_array($users)) {
            $users = collect($users);
        }

        $tokens = $users->pluck('fcm_token')->filter()->values()->toArray();

        if (empty($tokens)) {
            return;
        }

        foreach ($tokens as $token) {
            $this->sendToToken($token, $title, $body, $data);
        }
    }

    /**
     * Send to a single FCM token.
     * Note: This is a placeholder for Firebase HTTP v1 implementation.
     * It requires a service account JSON and OAuth2 token.
     */
    protected function sendToToken(string $token, string $title, string $body, array $data = []): void
    {
        try {
            $projectId = 'alamin-1e1ca'; // Hardcoded from user provided JSON
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => array_map('strval', $data), // FCM data values must be strings
                        'android' => [
                            'priority' => 'high',
                            'notification' => [
                                'sound' => 'default',
                                'channel_id' => 'default'
                            ]
                        ]
                    ]
                ]);

            if (!$response->successful()) {
                Log::error("FCM Send Error: " . $response->body());
            } else {
                Log::info("Push Notification successfully sent to Token: {$token}");
            }
        } catch (\Exception $e) {
            Log::error("Push Notification Exception: " . $e->getMessage());
        }
    }

    /**
     * Get OAuth2 Access Token for Firebase
     */
    protected function getAccessToken(): string
    {
        return cache()->remember('fcm_access_token', 3500, function () {
            $client = new \Google\Client();
            $client->setAuthConfig(storage_path('app/firebase-service-account.json'));
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $client->refreshTokenWithAssertion();
            $token = $client->getAccessToken();
            return $token['access_token'];
        });
    }
}
