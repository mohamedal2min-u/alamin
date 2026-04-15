<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يتحقق من أن المزرعة في حالة "active".
 * يُشغَّل بعد FarmScopeMiddleware الذي يضمن وجود farm_id في attributes.
 */
class CheckFarmActiveMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Farm مُحمَّل مسبقاً من FarmScopeMiddleware — لا حاجة لاستعلام جديد
        $farm = $request->attributes->get('farm');

        if ($farm->status !== 'active') {
            $statusMessages = [
                'pending_setup' => 'هذه المزرعة قيد الإعداد ولم تُفعَّل بعد',
                'suspended'     => 'هذه المزرعة موقوفة، يرجى التواصل مع المسؤول',
            ];

            return response()->json([
                'message' => $statusMessages[$farm->status] ?? 'هذه المزرعة غير نشطة',
            ], 403);
        }

        return $next($request);
    }
}
