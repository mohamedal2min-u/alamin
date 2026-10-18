<?php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Models\Flock;
use App\Services\FlockKpiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlockKpiController extends Controller
{
    public function __construct(private FlockKpiService $kpis) {}

    /** GET flocks/{flock}/kpis — مؤشرات أداء فوج واحد */
    public function show(Request $request, int $flock): JsonResponse
    {
        $model = Flock::where('farm_id', $request->attributes->get('farm_id'))->find($flock);
        if (! $model) {
            return response()->json(['message' => 'الفوج غير موجود أو لا ينتمي لهذه المزرعة'], 404);
        }

        return response()->json(['data' => $this->kpis->forFlock($model)]);
    }

    /** GET reports/flock-comparison — آخر 20 فوجاً (نشطاً أو مغلقاً) بمؤشراتها */
    public function comparison(Request $request): JsonResponse
    {
        $flocks = Flock::where('farm_id', $request->attributes->get('farm_id'))
            ->whereIn('status', ['active', 'closed'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return response()->json(['data' => $flocks->map(fn (Flock $f) => $this->kpis->forFlock($f))->values()]);
    }
}
