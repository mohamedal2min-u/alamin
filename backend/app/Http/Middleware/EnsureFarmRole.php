<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يقيّد المسار بأدوار المستخدم داخل المزرعة الحالية.
 * يُستخدم بعد farm.scope: farm.role:farm_admin,worker
 * مدير النظام (super_admin) يمرّ دائماً — FarmScopeMiddleware يحدد ذلك.
 */
class EnsureFarmRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if ($request->attributes->get('is_super_admin') === true) {
            return $next($request);
        }

        if (! $request->user()?->hasAnyRole($roles)) {
            return response()->json([
                'message' => 'ليس لديك صلاحية لتنفيذ هذه العملية',
            ], 403);
        }

        return $next($request);
    }
}
