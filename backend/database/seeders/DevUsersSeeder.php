<?php

namespace Database\Seeders;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DevUsersSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. إنشاء الأدوار (بدون team_id — تعريفات عامة) ──────────────────
        foreach (['super_admin', 'farm_admin', 'worker', 'partner'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // ── 2. إنشاء المستخدمين ───────────────────────────────────────────────
        $password = Hash::make('123456');

        $superAdmin = User::firstOrCreate(
            ['email' => 'mohamed@alamin.se'],
            ['name' => 'Mohamed — Super Admin', 'password' => $password, 'status' => 'active']
        );

        $farmAdmin = User::firstOrCreate(
            ['email' => '1@alamin.se'],
            ['name' => 'Farm Admin — 1', 'password' => $password, 'status' => 'active']
        );

        $worker = User::firstOrCreate(
            ['email' => '2@alamin.se'],
            ['name' => 'Worker — 2', 'password' => $password, 'status' => 'active']
        );

        $partner = User::firstOrCreate(
            ['email' => '3@alamin.se'],
            ['name' => 'Partner — 3', 'password' => $password, 'status' => 'active']
        );

        // ── 3. إنشاء المزرعة التجريبية ────────────────────────────────────────
        $farm = Farm::firstOrCreate(
            ['name' => 'مزرعة تجريبية'],
            [
                'status'       => 'active',
                'admin_user_id' => $farmAdmin->id,
                'started_at'   => now()->toDateString(),
                'created_by'   => $farmAdmin->id,
                'updated_by'   => $farmAdmin->id,
            ]
        );

        // ── 4. المستودع الرئيسي ───────────────────────────────────────────────
        Warehouse::firstOrCreate(
            ['farm_id' => $farm->id, 'name' => 'المستودع الرئيسي'],
            [
                'is_active'  => true,
                'created_by' => $farmAdmin->id,
                'updated_by' => $farmAdmin->id,
            ]
        );

        // ── 5. ربط الأعضاء بالمزرعة عبر farm_users ──────────────────────────
        $members = [
            ['user' => $farmAdmin, 'is_primary' => true],
            ['user' => $worker,    'is_primary' => false],
            ['user' => $partner,   'is_primary' => false],
        ];

        foreach ($members as ['user' => $user, 'is_primary' => $isPrimary]) {
            FarmUser::firstOrCreate(
                ['farm_id' => $farm->id, 'user_id' => $user->id],
                [
                    'status'     => 'active',
                    'is_primary' => $isPrimary,
                    'joined_at'  => now(),
                    'created_by' => $farmAdmin->id,
                    'updated_by' => $farmAdmin->id,
                ]
            );
        }

        // ── 6. تعيين الأدوار عبر Spatie Teams ────────────────────────────────

        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);

        // super_admin — دور عالمي بدون farm_id (team_id = null)
        $registrar->setPermissionsTeamId(null);
        $superAdmin->syncRoles([]);
        $superAdmin->assignRole('super_admin');

        // الأدوار المرتبطة بالمزرعة
        $registrar->setPermissionsTeamId($farm->id);
        $farmAdmin->syncRoles([]);
        $farmAdmin->assignRole('farm_admin');

        $worker->syncRoles([]);
        $worker->assignRole('worker');

        $partner->syncRoles([]);
        $partner->assignRole('partner');

        // إعادة ضبط الـ team ID بعد الانتهاء
        $registrar->setPermissionsTeamId(null);

        // ── 7. تسجيل في Console ───────────────────────────────────────────────
        $this->command->info('✔ DevUsersSeeder:');
        $this->command->table(
            ['Email', 'Role', 'Farm'],
            [
                ['mohamed@alamin.se', 'super_admin', '— (global)'],
                ['1@alamin.se',       'farm_admin',  $farm->name],
                ['2@alamin.se',       'worker',      $farm->name],
                ['3@alamin.se',       'partner',     $farm->name],
            ]
        );
        $this->command->info('Password: 123456');
    }
}
