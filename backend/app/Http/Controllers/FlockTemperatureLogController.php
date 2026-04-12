<?php

namespace App\Http\Controllers;

use App\Models\Flock;
use App\Models\FlockTemperatureLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FlockTemperatureLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, Flock $flock)
    {
        Gate::authorize('view', $flock->farm);

        $logs = FlockTemperatureLog::where('flock_id', $flock->id)
            ->where('farm_id', $flock->farm_id)
            ->orderBy('log_date', 'desc')
            ->orderBy('time_of_day', 'asc')
            ->get();

        return response()->json([
            'data' => $logs
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Flock $flock)
    {
        Gate::authorize('view', $flock->farm);
        
        $validated = $request->validate([
            'log_date' => 'required|date',
            'time_of_day' => 'required|in:morning,afternoon,evening',
            'temperature' => 'required|numeric|between:-50,100',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Use updateOrCreate since it's a unique combination
        $log = FlockTemperatureLog::updateOrCreate(
            [
                'flock_id' => $flock->id,
                'log_date' => $validated['log_date'],
                'time_of_day' => $validated['time_of_day'],
            ],
            [
                'farm_id' => $flock->farm_id,
                'temperature' => $validated['temperature'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]
        );

        return response()->json([
            'message' => 'Temperature log saved successfully',
            'data' => $log
        ], 201);
    }
}
