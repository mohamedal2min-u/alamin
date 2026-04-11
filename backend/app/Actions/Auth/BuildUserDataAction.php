<?php

namespace App\Actions\Auth;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * يبني مصفوفة بيانات المستخدم الكاملة بما فيها مزارعه وأدواره.
 * مستخدَم في LoginAction و MeAction.
 */
class BuildUserDataAction
{
    public function execute(User $user): array
    {
        // ── جلب الأدوار لكل مزرعة في استعلام واحد ────────────────────────────
        // team_foreign_key = 'farm_id' حسب config/permission.php
        $teamKey = config('permission.column_names.team_foreign_key', 'team_id');

        $rolesMap = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->whereNotNull("model_has_roles.{$teamKey}")
            ->pluck("roles.name", "model_has_roles.{$teamKey}");

        // ── تحقق من super_admin (دور عالمي بدون farm context) ────────────────
        $isSuperAdmin = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->whereNull("model_has_roles.{$teamKey}")
            ->where('roles.name', 'super_admin')
            ->exists();

        if ($isSuperAdmin) {
            $farms = Farm::select(['id', 'name', 'status'])
                ->orderBy('name')
                ->get()
                ->map(fn (Farm $farm) => [
                    'id'         => $farm->id,
                    'name'       => $farm->name,
                    'status'     => $farm->status,
                    'role'       => 'super_admin',
                    'is_primary' => true,
                ])->values();
        } else {
            $memberships = $user->farmMemberships()
                ->with('farm:id,name,status')
                ->where('status', 'active')
                ->get();

            $farms = $memberships->map(fn ($m) => [
                'id'         => $m->farm_id,
                'name'       => $m->farm->name,
                'status'     => $m->farm->status,
                'role'       => $rolesMap->get($m->farm_id),
                'is_primary' => (bool) $m->is_primary,
            ])->values();
        }

        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'whatsapp'    => $user->whatsapp,
            'avatar_path' => $user->avatar_path,
            'status'      => $user->status,
            'farms'       => $farms,
        ];
    }
}
