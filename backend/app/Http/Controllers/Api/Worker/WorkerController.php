<?php

namespace App\Http\Controllers\Api\Worker;

use App\Http\Controllers\Controller;
use App\Models\FarmUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class WorkerController extends Controller
{
    /**
     * تشغيل callback ضمن سياق team محدد ثم إعادة ضبط الـ context.
     */
    private function withTeamScope(int $farmId, callable $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($farmId);
        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId(null);
        }
    }

    /**
     * List all workers in the current farm.
     */
    public function index(Request $request)
    {
        $farm = $request->attributes->get('farm');

        $workers = $this->withTeamScope($farm->id, fn () =>
            User::role('worker')
                ->join('farm_users', 'users.id', '=', 'farm_users.user_id')
                ->where('farm_users.farm_id', $farm->id)
                ->select(
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.whatsapp',
                    'farm_users.salary',
                    'farm_users.joined_at',
                    'farm_users.status'
                )
                ->get()
        );

        return response()->json([
            'data' => $workers
        ]);
    }

    /**
     * Create a new worker and link to farm.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'nullable|string|email|max:255|unique:users,email',
            'whatsapp' => 'required|string|max:255|unique:users,whatsapp',
            'password' => 'required|string|min:8',
            'salary'   => 'nullable|numeric|min:0',
        ]);

        $farm = $request->attributes->get('farm');

        return DB::transaction(function () use ($validated, $farm, $request) {
            // 1. Create the user
            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'whatsapp' => $validated['whatsapp'] ?? null,
                'password' => Hash::make($validated['password']),
                'status'   => 'active',
            ]);

            // 2. Link the user to the farm
            FarmUser::create([
                'farm_id'    => $farm->id,
                'user_id'    => $user->id,
                'status'     => 'active',
                'is_primary' => false,
                'joined_at'  => now(),
                'salary'     => $validated['salary'] ?? null,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            // 3. Assign Role via Spatie (Contextualized by team_id = farm->id)
            $this->withTeamScope($farm->id, fn () => $user->assignRole('worker'));

            return response()->json([
                'message' => 'تم إنشاء حساب العامل بنجاح',
                'data'    => $user,
            ], 201);
        });
    }

    /**
     * Remove a worker from the farm.
     */
    public function destroy(Request $request, $id)
    {
        $farm = $request->attributes->get('farm');
        
        // Find the farm_user link
        $link = FarmUser::where('farm_id', $farm->id)
            ->where('user_id', $id)
            ->firstOrFail();

        // Optionally delete the user if they belong ONLY to this farm
        // For now, just remove the link to this farm
        $link->delete();

        // Also remove the role for this team context
        $user = User::findOrFail($id);
        $this->withTeamScope($farm->id, fn () => $user->removeRole('worker'));

        return response()->json([
            'message' => 'تم حذف العامل من المزرعة بنجاح'
        ]);
    }
}
