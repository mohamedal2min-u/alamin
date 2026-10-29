<?php

namespace App\Http\Middleware;

use App\Services\EditDetails;
use App\Services\EditNotifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * بعد نجاح الطلب يُبلَّغ الطرف الآخر بنغمة: farm.notify:<وصف التعديل>[,daily_entry]
 * daily_entry: تصحيح إدخال يومي يُبلَّغ به كل أعضاء المزرعة الآخرين.
 * يُرفق بالإشعار تفاصيل التعديل (قبل ← بعد) لتظهر عند الضغط عليه.
 * لا يعطّل الطلب أبداً، ويُرسَل بعد الاستجابة.
 */
class NotifyEdit
{
    public function handle(Request $request, Closure $next, string $what = 'تعديل جديد', ?string $kind = null): Response
    {
        try {
            $before = app(EditDetails::class)->snapshot($request);
        } catch (\Throwable $e) {
            $before = [];
        }

        $response = $next($request);

        if ($response->getStatusCode() < 300) {
            $farmId = $request->attributes->get('farm_id');
            $actor = $request->user();
            if ($farmId && $actor) {
                // التفاصيل تُبنى هنا (الطلب لا يُمرَّر للمهمة)، والإرسال بعد الاستجابة
                try {
                    $details = app(EditDetails::class)->build($request, $before, $kind, $actor->id);
                } catch (\Throwable $e) {
                    report($e);
                    $details = null;
                }
                dispatch(function () use ($details, $farmId, $actor, $what, $kind): void {
                    try {
                        app(EditNotifier::class)->notify((int) $farmId, $actor, $what, $kind, $details);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                })->afterResponse();
            }
        }

        return $response;
    }
}
