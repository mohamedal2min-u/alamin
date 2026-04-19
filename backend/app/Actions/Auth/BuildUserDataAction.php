<?php

namespace App\Actions\Auth;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

        // Priority: farm_admin > partner > worker
        // Group all roles per farm, then pick the highest priority one
        $rolePriority = ['farm_admin' => 3, 'partner' => 2, 'worker' => 1];

        $allRoles = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->whereNotNull("model_has_roles.{$teamKey}")
            ->select("model_has_roles.{$teamKey} as farm_id", 'roles.name as role_name')
            ->get();

        $rolesMap = $allRoles->groupBy('farm_id')->map(function ($roles) use ($rolePriority) {
            return $roles->sortByDesc(fn ($r) => $rolePriority[$r->role_name] ?? 0)
                         ->first()
                         ->role_name;
        });

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
                    'is_primary' => false,
                ])->values();
        } else {
            $memberships = $user->farmMemberships()
                ->with('farm:id,name,status')
                ->where('status', 'active')
                ->get();

            $partnerIds = \App\Models\Partner::where('user_id', $user->id)
                ->whereIn('farm_id', $memberships->pluck('farm_id'))
                ->pluck('id', 'farm_id');

            $farms = $memberships->map(function ($m) use ($rolesMap, $partnerIds) {
                $partnerId    = $partnerIds->get($m->farm_id);
                $spatieRole   = $rolesMap->get($m->farm_id);
                // Only override to 'partner' if Spatie didn't already assign a higher role (farm_admin).
                // farm_admin users also get a partner record via ensureManagerPartnerExists,
                // so we must not demote them.
                $role = ($partnerId && $spatieRole !== 'farm_admin') ? 'partner' : $spatieRole;

                // Skip farms where no role is assigned — user has a membership but no Spatie role.
                if (!$role) return null;

                return [
                    'id'         => $m->farm_id,
                    'name'       => $m->farm->name,
                    'status'     => $m->farm->status,
                    'role'       => $role,
                    'is_primary' => (bool) $m->is_primary,
                    'partner_id' => $partnerId,
                ];
            })->filter()->values();
        }

        $avatarUrl = $user->avatar_path
            ? url(Storage::url($user->avatar_path))
            : null;

        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'whatsapp'    => $user->whatsapp,
            'avatar_path' => $user->avatar_path,
            'avatar_url'  => $avatarUrl,
            'status'      => $user->status,
            'farms'       => $farms,
        ];
    }
}
