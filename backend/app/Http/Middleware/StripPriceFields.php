<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * مدير المزرعة يُدخل الكميات والسجلات لكنه لا يحدد الأسعار — تحديدها للمحاسب فقط.
 * يحذف حقول الأسعار من الطلب بصمت قبل التحقق، فيُنشأ السجل بلا سعر ويظهر في قائمة المحاسب.
 * يُستخدم بعد farm.scope: farm.noprice
 */
class StripPriceFields
{
    /** حقول السعر على المستوى الأعلى */
    private const FIELDS = [
        'unit_price', 'total_amount', 'default_cost',
        'chick_unit_price', 'discount_amount',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $isFarmAdminOnly = $request->attributes->get('is_super_admin') !== true
            && $request->user()?->hasRole('farm_admin')
            && ! $request->user()->hasRole('accountant');

        if ($isFarmAdminOnly) {
            foreach (self::FIELDS as $field) {
                $request->offsetUnset($field);
            }

            // أسطر البيع: سعر الكيلو
            $items = $request->input('items');
            if (is_array($items)) {
                foreach ($items as $i => $item) {
                    if (is_array($item)) {
                        unset($items[$i]['unit_price_per_kg'], $items[$i]['unit_price']);
                    }
                }
                $request->merge(['items' => $items]);
            }
        }

        return $next($request);
    }
}
