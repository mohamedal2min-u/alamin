<?php

namespace App\Http\Controllers\Api\DailyEntry;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Flock;
use App\Services\ActivityLogger;
use App\Services\DailyEntryCorrectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * تعديل/إلغاء الإدخالات اليومية + سجل النشاطات.
 * PATCH /api/daily-entries/{type}/{id}        تعديل (سبب اختياري)
 * POST  /api/daily-entries/{type}/{id}/void   إلغاء (سبب مطلوب)
 * GET   /api/activity-logs                    سجل النشاطات (مدير المزرعة)
 */
class DailyEntryController extends Controller
{
    private const RULES = [
        'mortality' => [
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ],
        'feed_log' => [
            'quantity' => ['sometimes', 'numeric', 'gt:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ],
        'medicine_log' => [
            'quantity' => ['sometimes', 'numeric', 'gt:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ],
        'water_log' => [
            'quantity' => ['sometimes', 'numeric', 'gt:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ],
        'note' => [
            'note_text' => ['sometimes', 'string', 'min:1', 'max:10000'],
            'note_type' => ['sometimes', 'string', 'in:general,instruction,operational,alert'],
        ],
        'temperature_log' => [
            'temperature' => ['sometimes', 'numeric', 'between:-50,100'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ],
    ];

    private const MESSAGES = [
        'quantity.min' => 'الكمية يجب أن تكون أكبر من صفر',
        'quantity.gt' => 'الكمية يجب أن تكون أكبر من صفر',
        'quantity.integer' => 'عدد النفوق يجب أن يكون رقماً صحيحاً',
        'temperature.between' => 'درجة الحرارة يجب أن تكون بين -50 و100',
        'reason.required' => 'سبب الإلغاء مطلوب',
        'reason.min' => 'اكتب سبباً واضحاً للإلغاء',
    ];

    public function __construct(private readonly DailyEntryCorrectionService $service) {}

    // ── PATCH /api/daily-entries/{type}/{id} ──────────────────────────────────

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        if (! isset(self::RULES[$type])) {
            return response()->json(['message' => 'نوع السجل غير معروف'], 404);
        }

        $validated = $request->validate(self::RULES[$type] + [
            'edit_reason' => ['nullable', 'string', 'max:500'],
        ], self::MESSAGES);

        $data = collect($validated)->except('edit_reason')->all();
        if ($data === []) {
            return response()->json(['message' => 'لا توجد تغييرات للحفظ'], 422);
        }

        return $this->guarded($request, $type, $id, function ($entry) use ($request, $type, $data, $validated) {
            $entry = $this->service->update($type, $entry, $data, $request->user()->id, $validated['edit_reason'] ?? null);

            return response()->json([
                'message' => 'تم تعديل السجل بنجاح',
                'data' => app(ActivityLogger::class)->snapshot($entry) + ['id' => $entry->id, 'entry_type' => $type],
            ]);
        });
    }

    // ── POST /api/daily-entries/{type}/{id}/void ──────────────────────────────

    public function void(Request $request, string $type, int $id): JsonResponse
    {
        if (! isset(self::RULES[$type])) {
            return response()->json(['message' => 'نوع السجل غير معروف'], 404);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ], self::MESSAGES);

        return $this->guarded($request, $type, $id, function ($entry) use ($request, $type, $validated) {
            $this->service->void($type, $entry, trim($validated['reason']), $request->user()->id);

            return response()->json(['message' => 'تم إلغاء السجل']);
        });
    }

    /** يجد السجل ضمن المزرعة ويتحقق من الصلاحية ثم ينفّذ، ويحوّل الأخطاء إلى رسائل */
    private function guarded(Request $request, string $type, int $id, \Closure $fn): JsonResponse
    {
        $farmId = (int) $request->attributes->get('farm_id');

        try {
            $entry = $this->service->find($farmId, $type, $id);
            $denial = $this->service->denial($request->user(), $request->attributes->get('is_super_admin') === true, $entry);
            if ($denial) {
                return response()->json(['message' => $denial[0]], $denial[1]);
            }

            return $fn($entry);
        } catch (\Exception $e) {
            $code = (int) $e->getCode();

            return response()->json(['message' => $e->getMessage()], $code >= 400 && $code < 600 ? $code : 422);
        }
    }

    // ── GET /api/activity-logs ────────────────────────────────────────────────

    public function activity(Request $request): JsonResponse
    {
        $farmId = (int) $request->attributes->get('farm_id');

        $filters = $request->validate([
            'flock_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'subject_type' => ['nullable', 'string', 'in:'.implode(',', array_keys(ActivityLogger::TYPES))],
            'action' => ['nullable', 'string', 'in:created,updated,voided'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $logs = ActivityLog::with(['user:id,name', 'flock:id,name'])
            ->where('farm_id', $farmId)
            ->when($filters['flock_id'] ?? null, fn ($q, $v) => $q->where('flock_id', $v))
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['subject_type'] ?? null, fn ($q, $v) => $q->where('subject_type', $v))
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
            ->orderByDesc('id')
            ->paginate(20);

        $userIds = ActivityLog::where('farm_id', $farmId)->whereNotNull('user_id')->distinct()->pluck('user_id');

        return response()->json([
            'data' => collect($logs->items())->map(fn (ActivityLog $l) => [
                'id' => $l->id,
                'action' => $l->action,
                'subject_type' => $l->subject_type,
                'subject_id' => $l->subject_id,
                'flock_id' => $l->flock_id,
                'flock_name' => $l->flock?->name,
                'user_id' => $l->user_id,
                'user_name' => $l->user?->name,
                'before' => $l->before,
                'after' => $l->after,
                'reason' => $l->reason,
                'created_at' => $l->created_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
            'filters' => [
                'flocks' => Flock::where('farm_id', $farmId)->orderByDesc('id')->get(['id', 'name']),
                'users' => \App\Models\User::whereIn('id', $userIds)->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }
}
