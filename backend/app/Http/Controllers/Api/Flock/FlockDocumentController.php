<?php

namespace App\Http\Controllers\Api\Flock;

use App\Actions\Flock\ShowFlockAction;
use App\Http\Controllers\Controller;
use App\Models\FlockDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlockDocumentController extends Controller
{
    private const RECORD_TYPES = ['sale', 'expense', 'water_log'];

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
            // مستندات قيد واحد فقط (فاتورة البيع/المصروف)
            ->when($request->filled('record_type'), fn ($q) => $q
                ->where('record_type', $request->query('record_type'))
                ->where('record_id', (int) $request->query('record_id')))
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
            'record_type' => ['nullable', 'in:'.implode(',', self::RECORD_TYPES)],
            'record_id'   => ['nullable', 'required_with:record_type', 'integer', 'min:1'],
        ], [
            'file.required' => 'اختر صورة',
            'file.image'    => 'الملف يجب أن يكون صورة',
            'file.max'      => 'حجم الصورة أكبر من 10 ميغا',
        ]);

        $path = $request->file('file')->store("farms/{$farmId}/flocks/{$flock->id}", 'public');

        $doc = FlockDocument::create([
            'farm_id'    => $farmId,
            'flock_id'   => $flock->id,
            'record_type' => $validated['record_type'] ?? null,
            'record_id'   => $validated['record_id'] ?? null,
            'title'      => $validated['title'] ?? null,
            'path'       => $path,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->present($doc)], 201);
    }

    // ── PATCH /api/flocks/{flock}/documents/{document} — ربط مستند قائم بقيد ──

    public function update(Request $request, int $flockId, int $documentId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        try {
            $flock = $this->showFlockAction->execute($farmId, $flockId);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $doc = FlockDocument::where('flock_id', $flock->id)->where('farm_id', $flock->farm_id)->findOrFail($documentId);

        $validated = $request->validate([
            'title'       => ['sometimes', 'nullable', 'string', 'max:190'],
            'record_type' => ['sometimes', 'nullable', 'in:'.implode(',', self::RECORD_TYPES)],
            'record_id'   => ['nullable', 'required_with:record_type', 'integer', 'min:1'],
        ]);

        $doc->fill($validated)->save();

        return response()->json(['data' => $this->present($doc)]);
    }

    private function present(FlockDocument $d): array
    {
        return [
            'id'         => $d->id,
            'title'      => $d->title,
            'record_type' => $d->record_type,
            'record_id'  => $d->record_id,
            'url'        => url(\Illuminate\Support\Facades\Storage::url($d->path)),
            'created_at' => $d->created_at?->toIso8601String(),
        ];
    }
}
