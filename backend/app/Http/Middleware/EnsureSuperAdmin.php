<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        if (! $request->user()?->hasRole('super_admin')) {
            abort(403, 'هذه العملية متاحة لمدير النظام فقط');
        }

        return $next($request);
    }
}
