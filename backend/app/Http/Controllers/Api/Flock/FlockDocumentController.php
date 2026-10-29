<?php

namespace App\Http\Controllers\Api\Flock;

use App\Actions\Flock\ShowFlockAction;
use App\Http\Controllers\Controller;
use App\Models\FlockDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlockDocumentController extends Controller
{
    public function __construct(
        private readonly ShowFlockAction $showFlockAction,
    ) {}

    // ── GET /api/flocks/{flock}/documents ─────────────────────────────────────

    public function index(Request $request, int $flockId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        try {
            $flock = $this->showFlockAction->execute($farmId, $flockId);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $docs = FlockDocument::where('flock_id', $flock->id)
            ->where('farm_id', $flock->farm_id)
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $docs->map(fn (FlockDocument $d) => $this->present($d))]);
    }

    // ── POST /api/flocks/{flock}/documents ────────────────────────────────────

    public function store(Request $request, int $flockId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        try {
            $flock = $this->showFlockAction->execute($farmId, $flockId);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $validated = $request->validate([
            'file'  => ['required', 'file', 'image', 'max:10240'], // Max 10MB
            'title' => ['nullable', 'string', 'max:190'],
        ], [
            'file.required' => 'اختر صورة',
            'file.image'    => 'الملف يجب أن يكون صورة',
            'file.max'      => 'حجم الصورة أكبر من 10 ميغا',
        ]);

        $path = $request->file('file')->store("farms/{$farmId}/flocks/{$flock->id}", 'public');

        $doc = FlockDocument::create([
            'farm_id'    => $farmId,
            'flock_id'   => $flock->id,
            'title'      => $validated['title'] ?? null,
            'path'       => $path,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->present($doc)], 201);
    }

    private function present(FlockDocument $d): array
    {
        return [
            'id'         => $d->id,
            'title'      => $d->title,
            'url'        => url(\Illuminate\Support\Facades\Storage::url($d->path)),
            'created_at' => $d->created_at?->toIso8601String(),
        ];
    }
}
