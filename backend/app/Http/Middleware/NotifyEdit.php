<?php

namespace App\Http\Middleware;

use App\Services\EditNotifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * بعد نجاح الطلب يُبلَّغ الطرف الآخر بنغمة: farm.notify:<وصف التعديل>
 * لا يعطّل الطلب أبداً، ويُرسَل بعد الاستجابة.
 */
class NotifyEdit
{
    public function handle(Request $request, Closure $next, string $what = 'تعديل جديد'): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() < 300) {
            $farmId = $request->attributes->get('farm_id');
            $actor = $request->user();
            if ($farmId && $actor) {
                dispatch(function () use ($farmId, $actor, $what): void {
                    try {
                        app(EditNotifier::class)->notify((int) $farmId, $actor, $what);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                })->afterResponse();
            }
        }

        return $response;
    }
}
