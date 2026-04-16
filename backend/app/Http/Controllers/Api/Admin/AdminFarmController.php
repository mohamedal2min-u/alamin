<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class AdminFarmController extends Controller
{
    // ── GET /api/admin/farms ──────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $farms = Farm::select(['id', 'name', 'location', 'status', 'started_at', 'created_at', 'admin_user_id'])
            ->with(['adminUser:id,name,email'])
            ->withCount(['farmMemberships as members_count'])
            ->orderBy('name')
            ->get()
            ->map(fn (Farm $farm) => [
                'id'           => $farm->id,
                'name'         => $farm->name,
                'location'     => $farm->location,
                'status'       => $farm->status,
                'started_at'   => $farm->started_at?->toDateString(),
                'created_at'   => $farm->created_at?->toISOString(),
                'members_count' => $farm->members_count,
                'admin'        => $farm->adminUser
                    ? ['id' => $farm->adminUser->id, 'name' => $farm->adminUser->name]
                    : null,
            ]);

        return response()->json(['data' => $farms], 200);
    }

    // ── GET /api/admin/farms/{farm} ───────────────────────────────────────────

    public function show(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $farm->load(['adminUser:id,name,email']);

        return response()->json([
            'data' => [
                'id'         => $farm->id,
                'name'       => $farm->name,
                'location'   => $farm->location,
                'status'     => $farm->status,
                'started_at' => $farm->started_at?->toDateString(),
                'created_at' => $farm->created_at?->toISOString(),
                'admin'      => $farm->adminUser
                    ? ['id' => $farm->adminUser->id, 'name' => $farm->adminUser->name, 'email' => $farm->adminUser->email]
                    : null,
            ]
        ], 200);
    }

    // ── POST /api/admin/farms ─────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $data = $request->validate([
            'name'       => 'required|string|max:190|unique:farms,name',
            'location'   => 'nullable|string|max:500',
            'started_at' => 'nullable|date|date_format:Y-m-d',
        ], [
            'name.required'          => 'اسم المزرعة مطلوب',
            'name.max'               => 'اسم المزرعة طويل جداً',
            'name.unique'            => 'يوجد مزرعة بهذا الاسم مسبقاً',
            'started_at.date_format' => 'صيغة التاريخ غير صحيحة (YYYY-MM-DD)',
        ]);

        $farm = Farm::create([
            'name'       => $data['name'],
            'location'   => $data['location'] ?? null,
            'status'     => 'active',
            'started_at' => $data['started_at'] ?? null,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'تم إنشاء المزرعة بنجاح',
            'data'    => [
                'id'          => $farm->id,
                'name'        => $farm->name,
                'location'    => $farm->location,
                'status'      => $farm->status,
                'started_at'  => $farm->started_at?->toDateString(),
                'created_at'  => $farm->created_at?->toISOString(),
                'members_count' => 0,
                'admin'       => null,
            ],
        ], 201);
    }

    // ── PUT /api/admin/farms/{farm}/admin ─────────────────────────────────────

    public function assignAdmin(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ], [
            'user_id.required' => 'يجب اختيار المستخدم',
            'user_id.exists'   => 'المستخدم غير موجود',
        ]);

        $newAdmin = User::findOrFail($data['user_id']);

        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);

        // ── إزالة دور farm_admin من المدير القديم ─────────────────────────────
        if ($farm->admin_user_id && $farm->admin_user_id !== $newAdmin->id) {
            $oldAdmin = User::find($farm->admin_user_id);
            if ($oldAdmin) {
                $registrar->setPermissionsTeamId($farm->id);
                $oldAdmin->removeRole('farm_admin');
            }
        }

        // ── إضافة المستخدم الجديد لـ farm_users إن لم يكن عضواً ──────────────
        FarmUser::firstOrCreate(
            ['farm_id' => $farm->id, 'user_id' => $newAdmin->id],
            [
                'status'     => 'active',
                'is_primary' => true,
                'joined_at'  => now(),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]
        );

        // تفعيل العضوية وضبط is_primary
        FarmUser::where('farm_id', $farm->id)
            ->where('user_id', $newAdmin->id)
            ->update(['status' => 'active', 'is_primary' => true, 'updated_by' => $request->user()->id]);

        // ── تعيين دور farm_admin ───────────────────────────────────────────────
        $registrar->setPermissionsTeamId($farm->id);
        if (! $newAdmin->hasRole('farm_admin')) {
            $newAdmin->assignRole('farm_admin');
        }

        // ── تحديث admin_user_id على المزرعة ──────────────────────────────────
        $farm->update([
            'admin_user_id' => $newAdmin->id,
            'updated_by'    => $request->user()->id,
        ]);

        // إعادة ضبط الـ team context
        $registrar->setPermissionsTeamId(null);

        return response()->json([
            'message' => 'تم تعيين مدير المزرعة بنجاح',
            'data'    => [
                'farm_id' => $farm->id,
                'admin'   => ['id' => $newAdmin->id, 'name' => $newAdmin->name],
            ],
        ], 200);
    }

    // ── GET /api/admin/users ──────────────────────────────────────────────────

    public function users(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $users = User::select(['id', 'name', 'email', 'whatsapp', 'status'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $users], 200);
    }

    // ── POST /api/admin/users ──────────────────────────────────────────────────
    // إنشاء مستخدم جديد يدوياً (مدير مزرعة)
    public function storeUser(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $data = $request->validate([
            'name'     => 'required|string|max:190',
            'email'    => 'nullable|email|unique:users,email',
            'whatsapp' => 'required|string|unique:users,whatsapp',
            'password' => 'required|string|min:6',
        ], [
            'name.required'     => 'الاسم مطلوب',
            'whatsapp.required' => 'رقم الواتساب مطلوب',
            'whatsapp.unique'   => 'رقم الواتساب مسجل مسبقاً',
            'email.unique'      => 'البريد الإلكتروني مسجل مسبقاً',
            'password.min'      => 'كلمة المرور يجب أن لا تقل عن 6 أحرف',
        ]);

        $user = User::create([
            'name'       => $data['name'],
            'email'      => $data['email'] ?? null,
            'whatsapp'   => $data['whatsapp'],
            'password'   => bcrypt($data['password']),
            'status'     => 'active',
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'تم إنشاء المستخدم بنجاح',
            'data'    => $user
        ], 201);
    }

    // ── DELETE /api/admin/farms/{farm} ────────────────────────────────────────
    public function destroy(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $farmName = $farm->name;
        $farm->delete(); // Cascade handles children

        return response()->json([
            'message' => "تم حذف المزرعة \"$farmName\" بنجاح"
        ], 200);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function authorizeSuperAdmin(Request $request): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        if (! $request->user()->hasRole('super_admin')) {
            abort(403, 'هذه العملية متاحة لمدير النظام فقط');
        }
    }
}
