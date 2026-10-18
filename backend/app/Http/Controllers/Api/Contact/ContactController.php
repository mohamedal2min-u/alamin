<?php

namespace App\Http\Controllers\Api\Contact;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactRequest;
use App\Models\Contact;
use App\Models\InventoryTransaction;
use App\Models\Sale;
use App\Services\ContactStatementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * الموردون والتجار (المشترون) لكل مزرعة.
 */
class ContactController extends Controller
{
    public function __construct(private readonly ContactStatementService $statements) {}

    /** GET /contacts — قائمة خفيفة لمنتقي الجهة (type=supplier|buyer يشمل «كلاهما») */
    public function index(Request $request): JsonResponse
    {
        $farmId = $request->attributes->get('farm_id');
        $type = $request->query('type');
        $q = trim((string) $request->query('q', ''));

        $contacts = Contact::where('farm_id', $farmId)
            ->when(in_array($type, ['supplier', 'buyer'], true), fn ($query) => $query->whereIn('type', [$type, 'both']))
            ->when($request->boolean('active'), fn ($query) => $query->where('is_active', true))
            ->when($q !== '', fn ($query) => $query->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%'))
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'type', 'phone', 'whatsapp', 'notes', 'is_active']);

        return response()->json(['data' => $contacts]);
    }

    /** POST /contacts */
    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = Contact::create([
            ...$request->validated(),
            'farm_id' => $request->attributes->get('farm_id'),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'تمت الإضافة', 'data' => $contact], 201);
    }

    /** PUT /contacts/{contact} — تغيير الاسم يُزامَن مع الحقول النصية القديمة */
    public function update(StoreContactRequest $request, int $contact): JsonResponse
    {
        $model = $this->find($request, $contact);
        $oldName = $model->name;

        DB::transaction(function () use ($model, $request, $oldName): void {
            $model->update([...$request->validated(), 'updated_by' => $request->user()->id]);

            if ($model->name !== $oldName) {
                Sale::where('farm_id', $model->farm_id)->where('contact_id', $model->id)->update(['buyer_name' => $model->name]);
                InventoryTransaction::where('farm_id', $model->farm_id)->where('contact_id', $model->id)->update(['supplier_name' => $model->name]);
            }
        });

        return response()->json(['message' => 'تم التحديث', 'data' => $model->fresh()]);
    }

    /** DELETE /contacts/{contact} — جهة لها سجلات لا تُحذف (تُعطَّل بدلاً من ذلك) */
    public function destroy(Request $request, int $contact): JsonResponse
    {
        $model = $this->find($request, $contact);

        if ($model->isReferenced()) {
            return response()->json(['message' => 'لهذه الجهة سجلات مالية؛ عطّلها بدلاً من حذفها كي يبقى كشف حسابها'], 422);
        }

        $model->delete();

        return response()->json(['message' => 'تم الحذف']);
    }

    /** GET /contacts/balances — الأرصدة وأعمار الديون */
    public function balances(Request $request): JsonResponse
    {
        return response()->json($this->statements->balances(
            (int) $request->attributes->get('farm_id'),
            ['type' => $request->query('type'), 'active' => $request->boolean('active')]
        ));
    }

    /** GET /contacts/{contact}/statement?from=&to= — كشف حساب */
    public function statement(Request $request, int $contact): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return response()->json($this->statements->statement(
            $this->find($request, $contact),
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        ));
    }

    private function find(Request $request, int $id): Contact
    {
        return Contact::where('farm_id', $request->attributes->get('farm_id'))->findOrFail($id);
    }
}
