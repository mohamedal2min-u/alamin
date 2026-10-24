<?php

namespace App\Http\Middleware;

use App\Models\Flock;
use App\Services\EditNotifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * بعد نجاح الطلب يُبلَّغ باقي أعضاء المزرعة بنغمة: farm.notify:<وصف التعديل>[,daily_entry]
 * (daily_entry = سجل يومي بنغمة بسيطة؛ بدونه تُختار نغمة المحاسب أو المدير حسب من عدّل)
 * لا يعطّل الطلب أبداً، ويُرسَل بعد الاستجابة.
 */
class NotifyEdit
{
    public function handle(Request $request, Closure $next, string $what = 'تعديل جديد', ?string $kind = null): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() < 300) {
            $farmId = $request->attributes->get('farm_id');
            $actor = $request->user();
            if ($farmId && $actor) {
                $flock = $request->route('flock');
                $flockName = match (true) {
                    $flock instanceof Flock => $flock->name,
                    is_numeric($flock) => Flock::where('farm_id', $farmId)->whereKey((int) $flock)->value('name'),
                    default => null,
                };
                if ($flockName) {
                    $what .= " — {$flockName}";
                }
                dispatch(function () use ($farmId, $actor, $what, $kind): void {
                    try {
                        app(EditNotifier::class)->notify((int) $farmId, $actor, $what, $kind);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                })->afterResponse();
            }
        }

        return $response;
    }
}
