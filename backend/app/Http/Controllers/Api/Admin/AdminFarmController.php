<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\User;
use App\Services\Partner\PartnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

        // ── Ensure Admin Partner & 100% Share ───────────────────────────────
        app(PartnerService::class)->ensureManagerPartnerExists($farm->id);

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

    // ── POST /api/admin/farms/{farm}/manager ─────────────────────────────────

    public function createManager(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $data = $request->validate([
            'name'     => 'required|string|max:150',
            'whatsapp' => 'required|string|max:30|unique:users,whatsapp',
            'email'    => 'nullable|email|max:190|unique:users,email',
            'password' => 'required|string|min:8',
        ], [
            'name.required'      => 'الاسم مطلوب',
            'whatsapp.required'  => 'رقم الواتساب مطلوب',
            'whatsapp.unique'    => 'هذا الرقم مسجّل مسبقاً',
            'email.email'        => 'صيغة البريد الإلكتروني غير صحيحة',
            'email.unique'       => 'هذا البريد مسجّل مسبقاً',
            'password.min'       => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
        ]);

        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);

        // إنشاء المستخدم الجديد
        $manager = User::create([
            'name'     => $data['name'],
            'whatsapp' => $data['whatsapp'],
            'email'    => $data['email'] ?? null,
            'password' => Hash::make($data['password']),
            'status'   => 'active',
        ]);

        // إضافته كعضو في المزرعة
        FarmUser::firstOrCreate(
            ['farm_id' => $farm->id, 'user_id' => $manager->id],
            [
                'status'     => 'active',
                'is_primary' => true,
                'joined_at'  => now(),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]
        );

        FarmUser::where('farm_id', $farm->id)
            ->where('user_id', $manager->id)
            ->update(['status' => 'active', 'is_primary' => true, 'updated_by' => $request->user()->id]);

        // تعيين دور farm_admin
        $registrar->setPermissionsTeamId($farm->id);
        $manager->assignRole('farm_admin');
        $registrar->setPermissionsTeamId(null);

        // تحديث admin_user_id
        $farm->update([
            'admin_user_id' => $manager->id,
            'updated_by'    => $request->user()->id,
        ]);

        // ── Ensure Admin Partner & 100% Share ───────────────────────────────
        app(PartnerService::class)->ensureManagerPartnerExists($farm->id);

        return response()->json([
            'message' => 'تم إنشاء حساب المدير بنجاح',
            'data'    => [
                'farm_id' => $farm->id,
                'admin'   => ['id' => $manager->id, 'name' => $manager->name],
            ],
        ], 201);
    }

    // ── DELETE /api/admin/farms/{farm} ────────────────────────────────────────

    public function destroy(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $farmId = $farm->id;

        DB::transaction(function () use ($farmId) {
            // ── 1. Flock-level operational logs ──────────────────────────────
            DB::table('flock_mortalities')->where('farm_id', $farmId)->delete();
            DB::table('flock_feed_logs')->where('farm_id', $farmId)->delete();
            DB::table('flock_medicines')->where('farm_id', $farmId)->delete();
            DB::table('flock_water_logs')->where('farm_id', $farmId)->delete();
            DB::table('flock_notes')->where('farm_id', $farmId)->delete();
            DB::table('flock_temperature_logs')->where('farm_id', $farmId)->delete();

            // ── 2. Expenses & financial records ──────────────────────────────
            DB::table('expenses')->where('farm_id', $farmId)->delete();

            // ── 3. Sales ──────────────────────────────────────────────────────
            DB::table('sale_items')->where('farm_id', $farmId)->delete();
            DB::table('sales')->where('farm_id', $farmId)->delete();

            // ── 4. Inventory ──────────────────────────────────────────────────
            DB::table('inventory_transactions')->where('farm_id', $farmId)->delete();
            DB::table('warehouse_items')->where('farm_id', $farmId)->delete();
            DB::table('warehouses')->where('farm_id', $farmId)->delete();
            DB::table('items')->where('farm_id', $farmId)->delete();

            // ── 5. Flocks (after all flock-dependent records gone) ────────────
            DB::table('flocks')->where('farm_id', $farmId)->delete();

            // ── 6. Partners & shares ──────────────────────────────────────────
            DB::table('partner_transactions')->where('farm_id', $farmId)->delete();
            DB::table('farm_partner_shares')->where('farm_id', $farmId)->delete();
            DB::table('partners')->where('farm_id', $farmId)->delete();

            // ── 7. Spatie roles for this farm ─────────────────────────────────
            DB::table('model_has_roles')->where('farm_id', $farmId)->delete();

            // ── 8. Farm memberships (cascade is set but be explicit) ──────────
            DB::table('farm_users')->where('farm_id', $farmId)->delete();

            // ── 9. Farm itself ────────────────────────────────────────────────
            DB::table('farms')->where('id', $farmId)->delete();
        });

        return response()->json(['message' => 'تم حذف المزرعة وجميع بياناتها بنجاح'], 200);
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

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function authorizeSuperAdmin(Request $request): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        if (! $request->user()->hasRole('super_admin')) {
            abort(403, 'هذه العملية متاحة لمدير النظام فقط');
        }
    }
}
