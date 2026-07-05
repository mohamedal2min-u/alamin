<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
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
}
