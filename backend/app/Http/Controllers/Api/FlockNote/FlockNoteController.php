<?php

namespace App\Http\Controllers\Api\FlockNote;

use App\Actions\Flock\ShowFlockAction;
use App\Actions\FlockNote\CreateFlockNoteAction;
use App\Actions\FlockNote\ListFlockNotesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\FlockNote\StoreFlockNoteRequest;
use App\Http\Resources\FlockNoteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class FlockNoteController extends Controller
{
    public function __construct(
        private readonly ShowFlockAction       $showFlockAction,
        private readonly ListFlockNotesAction  $listAction,
        private readonly CreateFlockNoteAction $createAction,
    ) {}

    // ── GET /api/flocks/{flock}/notes ─────────────────────────────────────────

    public function index(Request $request, int $flockId): ResourceCollection|JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');

        try {
            $this->showFlockAction->execute($farmId, $flockId);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 404);
        }

        $notes = $this->listAction->execute($farmId, $flockId);

        return FlockNoteResource::collection($notes);
    }

    // ── POST /api/flocks/{flock}/notes ────────────────────────────────────────

    public function store(StoreFlockNoteRequest $request, int $flockId): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $userId = $request->user()->id;

        try {
            $flock = $this->showFlockAction->execute($farmId, $flockId);
            $note  = $this->createAction->execute($flock, $userId, $request->validated());
        } catch (\Exception $e) {
            $code = (int) $e->getCode();
            return response()->json(
                ['message' => $e->getMessage()],
                $code >= 400 && $code < 600 ? $code : 422
            );
        }

        return response()->json([
            'message' => 'تمت إضافة الملاحظة بنجاح',
            'data'    => new FlockNoteResource($note),
        ], 201);
    }
}
