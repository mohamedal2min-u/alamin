<?php

namespace App\Http\Middleware;

use App\Models\Farm;
use App\Models\FarmUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يضبط سياق المزرعة لكل request عبر:
 *  1. قراءة X-Farm-Id من الـ header
 *  2. التحقق من عضوية المستخدم في المزرعة
 *  3. ضبط Spatie Teams context عبر setPermissionsTeamId()
 *  4. تخزين farm_id في request attributes للاستخدام لاحقاً
 */
class FarmScopeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $farmId = $request->header('X-Farm-Id');

        if (! $farmId || ! ctype_digit((string) $farmId)) {
            return response()->json([
                'message' => 'يجب تحديد المزرعة عبر header: X-Farm-Id',
            ], 400);
        }

        $farmId = (int) $farmId;
        $user   = $request->user();

        // ── تحميل المزرعة مرة واحدة ويُمرَّر عبر attributes للـ middleware التالي ──
        $farm = Farm::find($farmId);

        if (! $farm) {
            return response()->json(['message' => 'المزرعة غير موجودة'], 404);
        }

        // ── super_admin: دور عالمي (farm_id = null في model_has_roles) ────────
        // نتحقق منه قبل ضبط team context لأن hasRole يبحث ضمن الـ team الحالي
        $isSuperAdmin = $user->hasRole('super_admin');

        if (! $isSuperAdmin) {
            // بقية الأدوار: يجب أن يكون المستخدم عضواً نشطاً في المزرعة
            $isMember = FarmUser::where('farm_id', $farmId)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();

            if (! $isMember) {
                return response()->json([
                    'message' => 'ليس لديك صلاحية الوصول لهذه المزرعة',
                ], 403);
            }
        }

        // ── ضبط Spatie Teams context ──────────────────────────────────────────
        setPermissionsTeamId($farmId);

        // ── تخزين farm_id والـ Farm model في attributes لتجنب إعادة الاستعلام ──
        $request->attributes->set('farm_id', $farmId);
        $request->attributes->set('farm', $farm);

        return $next($request);
    }
}
