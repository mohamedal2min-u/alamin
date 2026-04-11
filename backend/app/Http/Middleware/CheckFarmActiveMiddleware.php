<?php

namespace App\Http\Middleware;

use App\Models\Farm;
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
        $farmId = $request->attributes->get('farm_id');

        $farm = Farm::find($farmId);

        if (! $farm) {
            return response()->json(['message' => 'المزرعة غير موجودة'], 404);
        }

        if ($farm->status !== 'active') {
            $statusMessages = [
                'pending_setup' => 'هذه المزرعة قيد الإعداد ولم تُفعَّل بعد',
                'suspended'     => 'هذه المزرعة موقوفة، يرجى التواصل مع المسؤول',
            ];

            return response()->json([
                'message' => $statusMessages[$farm->status] ?? 'هذه المزرعة غير نشطة',
            ], 403);
        }

        // نخزّن نموذج المزرعة لتجنّب إعادة الاستعلام في Controllers
        $request->attributes->set('farm', $farm);

        return $next($request);
    }
}
