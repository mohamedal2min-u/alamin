<?php

namespace App\Http\Controllers\Api\Vaccination;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vaccination\UpdateFlockVaccinationRequest;
use App\Http\Requests\Vaccination\UpdateVaccineTemplateRequest;
use App\Models\Flock;
use App\Models\FlockVaccination;
use App\Models\VaccineScheduleTemplate;
use App\Services\VaccinationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VaccinationController extends Controller
{
    public function __construct(private readonly VaccinationService $service) {}

    // ── GET /api/vaccine-template ─────────────────────────────────────────────

    public function template(Request $request): JsonResponse
    {
        $template = $this->service->template($request->attributes->get('farm_id'));

        return response()->json(['data' => $this->templatePayload($template)]);
    }

    // ── PUT /api/vaccine-template — مدير المزرعة فقط ──────────────────────────
    // لا يعيد كتابة لقاحات الأفواج الحالية (استخدم «تطبيق على الفوج الحالي»)

    public function updateTemplate(UpdateVaccineTemplateRequest $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $userId = $request->user()->id;
        $data = $request->validated();
        $template = $this->service->template($farmId);

        DB::transaction(function () use ($template, $data, $userId): void {
            $template->update(['name' => $data['name'], 'is_example' => false, 'updated_by' => $userId]);
            $template->items()->delete();
            $template->items()->createMany(array_map(fn ($i) => [
                'age_day'         => (int) $i['age_day'],
                'title'           => $i['title'],
                'method'          => $i['method'],
                'notes'           => $i['notes'] ?? null,
                'withdrawal_days' => isset($i['withdrawal_days']) && $i['withdrawal_days'] !== '' ? (int) $i['withdrawal_days'] : null,
            ], $data['items']));
        });

        return response()->json([
            'message' => 'تم حفظ برنامج اللقاحات',
            'data'    => $this->templatePayload($template->fresh()),
        ]);
    }

    // ── POST /api/vaccine-template/apply-active — «تطبيق على الفوج الحالي» ──

    public function applyToActive(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $flock = Flock::where('farm_id', $farmId)->where('status', 'active')->first();
        if (! $flock) {
            return response()->json(['message' => 'لا يوجد فوج نشط'], 422);
        }

        $created = $this->service->regenerateDue($flock);

        return response()->json([
            'message' => 'تم تطبيق البرنامج على الفوج الحالي',
            'data'    => ['flock_id' => $flock->id, 'created' => $created],
        ]);
    }

    // ── GET /api/vaccinations/active — لقاحات الفوج النشط (البطاقة والتنبيهات) ─

    public function active(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $flock = Flock::where('farm_id', $farmId)->where('status', 'active')->first();
        if (! $flock) {
            return response()->json(['flock' => null, 'data' => []]);
        }

        $this->service->generateIfMissing($flock);

        $rows = FlockVaccination::where('flock_id', $flock->id)
            ->where('status', 'due')
            ->orderBy('due_date')->orderBy('id')
            ->get();

        return response()->json([
            'flock' => ['id' => $flock->id, 'name' => $flock->name, 'start_date' => $flock->start_date->toDateString()],
            'data'  => $rows->map(fn ($v) => $this->rowPayload($v))->values(),
        ]);
    }

    // ── GET /api/flocks/{flock}/vaccinations ──────────────────────────────────

    public function index(Request $request, int $flockId): JsonResponse
    {
        $flock = $this->findFlock($request, $flockId);
        if (! $flock) {
            return response()->json(['message' => 'الفوج غير موجود'], 404);
        }

        $this->service->generateIfMissing($flock);

        $rows = FlockVaccination::where('flock_id', $flock->id)
            ->with('doneByUser:id,name')
            ->orderBy('due_date')->orderBy('id')
            ->get();

        return response()->json([
            'data'       => $rows->map(fn ($v) => $this->rowPayload($v))->values(),
            'withdrawal' => $this->service->withdrawal($flock),
        ]);
    }

    // ── PATCH /api/flocks/{flock}/vaccinations/{vaccination} — مدير/مشرف ─────

    public function update(UpdateFlockVaccinationRequest $request, int $flockId, int $vaccinationId): JsonResponse
    {
        $flock = $this->findFlock($request, $flockId);
        if (! $flock) {
            return response()->json(['message' => 'الفوج غير موجود'], 404);
        }
        if ($flock->status !== 'active') {
            return response()->json(['message' => 'لا يمكن تعديل لقاحات فوج غير نشط'], 422);
        }

        $vaccination = FlockVaccination::where('flock_id', $flock->id)->where('id', $vaccinationId)->first();
        if (! $vaccination) {
            return response()->json(['message' => 'اللقاح غير موجود'], 404);
        }

        $status = $request->validated('status');
        $vaccination->update([
            'status'  => $status,
            'done_at' => $status === 'due' ? null : now(),
            'done_by' => $status === 'due' ? null : $request->user()->id,
            'note'    => $request->validated('note'),
        ]);

        $vaccination->load('doneByUser:id,name');

        return response()->json([
            'message'    => match ($status) {
                'done'    => 'تم تسجيل اللقاح',
                'skipped' => 'تم تخطي اللقاح',
                default   => 'أُعيد اللقاح إلى قائمة المستحق',
            },
            'data'       => $this->rowPayload($vaccination),
            'withdrawal' => $this->service->withdrawal($flock),
        ]);
    }

    // ── GET /api/flocks/{flock}/withdrawal — تاريخ «لا بيع قبل» ───────────────

    public function withdrawal(Request $request, int $flockId): JsonResponse
    {
        $flock = $this->findFlock($request, $flockId);
        if (! $flock) {
            return response()->json(['message' => 'الفوج غير موجود'], 404);
        }

        return response()->json(['data' => $this->service->withdrawal($flock)]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function findFlock(Request $request, int $flockId): ?Flock
    {
        return Flock::where('id', $flockId)->where('farm_id', $request->attributes->get('farm_id'))->first();
    }

    private function templatePayload(VaccineScheduleTemplate $template): array
    {
        return [
            'id'         => $template->id,
            'name'       => $template->name,
            'is_active'  => $template->is_active,
            'is_example' => $template->is_example,
            'items'      => $template->items()->get()->map(fn ($i) => [
                'id'              => $i->id,
                'age_day'         => $i->age_day,
                'title'           => $i->title,
                'method'          => $i->method,
                'notes'           => $i->notes,
                'withdrawal_days' => $i->withdrawal_days,
            ])->values(),
        ];
    }

    private function rowPayload(FlockVaccination $v): array
    {
        return [
            'id'              => $v->id,
            'flock_id'        => $v->flock_id,
            'age_day'         => $v->age_day,
            'due_date'        => $v->due_date->toDateString(),
            'title'           => $v->title,
            'method'          => $v->method,
            'notes'           => $v->notes,
            'status'          => $v->status,
            'done_at'         => $v->done_at?->toISOString(),
            'done_by'         => $v->relationLoaded('doneByUser') && $v->doneByUser
                ? ['id' => $v->doneByUser->id, 'name' => $v->doneByUser->name]
                : null,
            'note'            => $v->note,
            'withdrawal_days' => $v->withdrawal_days,
        ];
    }
}
