<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Update the user's FCM token.
     */
    public function updateToken(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        try {
            $user = $request->user();
            $user->update([
                'fcm_token' => $request->fcm_token,
            ]);

            return response()->json([
                'message' => 'تم تحديث رمز الإشعارات بنجاح',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'فشل تحديث رمز الإشعارات',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /auth/notifications/feed?after_id=N
     * بدون after_id: يرجع آخر معرّف فقط (نقطة البداية) دون إشعارات قديمة.
     * معه: الإشعارات الأحدث منه، لتصدر النغمة المناسبة في التطبيق المفتوح.
     */
    public function feed(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $last = (int) (UserNotification::where('user_id', $userId)->max('id') ?? 0);

        if (! $request->filled('after_id')) {
            return response()->json(['data' => [], 'last_id' => $last]);
        }

        $items = UserNotification::where('user_id', $userId)
            ->where('id', '>', (int) $request->query('after_id'))
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'kind', 'title', 'body', 'created_at']);

        return response()->json(['data' => $items, 'last_id' => max($last, (int) $request->query('after_id'))]);
    }
}
