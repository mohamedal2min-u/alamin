<?php

namespace App\Http\Controllers\Api\Flock;

use App\Actions\Flock\ShowFlockAction;
use App\Http\Controllers\Controller;
use App\Models\FlockTemperatureLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlockTemperatureLogController extends Controller
{
    public function __construct(
        private readonly ShowFlockAction $showFlockAction,
    ) {}

    // ── GET /api/flocks/{flock}/temperature-logs ──────────────────────────────

    public function index(Request $request, int $flockId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        try {
            $flock = $this->showFlockAction->execute($farmId, $flockId);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $logs = FlockTemperatureLog::where('flock_id', $flock->id)
            ->where('farm_id', $flock->farm_id)
            ->orderBy('log_date', 'desc')
            ->orderBy('time_of_day', 'asc')
            ->get();

        return response()->json(['data' => $logs]);
    }

    // ── POST /api/flocks/{flock}/temperature-logs ─────────────────────────────

    public function store(Request $request, int $flockId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        try {
            $flock = $this->showFlockAction->execute($farmId, $flockId);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $validated = $request->validate([
            'log_date'    => 'required|date_format:Y-m-d',
            'time_of_day' => 'required|in:morning,afternoon,evening',
            'temperature' => 'required|numeric|between:-50,100',
            'notes'       => 'nullable|string|max:1000',
        ], [
            'log_date.required'    => 'تاريخ القراءة مطلوب',
            'log_date.date_format' => 'صيغة التاريخ يجب أن تكون YYYY-MM-DD',
            'time_of_day.required' => 'وقت القراءة مطلوب',
            'time_of_day.in'       => 'وقت القراءة يجب أن يكون: morning أو afternoon أو evening',
            'temperature.required' => 'درجة الحرارة مطلوبة',
            'temperature.between'  => 'درجة الحرارة يجب أن تكون بين -50 و100',
        ]);

        $log = FlockTemperatureLog::updateOrCreate(
            [
                'flock_id'    => $flock->id,
                'log_date'    => $validated['log_date'],
                'time_of_day' => $validated['time_of_day'],
            ],
            [
                'farm_id'     => $flock->farm_id,
                'temperature' => $validated['temperature'],
                'notes'       => $validated['notes'] ?? null,
                'created_by'  => $request->user()->id,
            ]
        );

        return response()->json([
            'message' => 'تم حفظ درجة الحرارة بنجاح',
            'data'    => $log,
        ], 201);
    }
}
