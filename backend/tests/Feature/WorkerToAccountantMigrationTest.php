<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkerToAccountantMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_roles_and_assignments_become_accountant(): void
    {
        $farm = Farm::factory()->create();
        $user = User::factory()->create();
        FarmUser::factory()->withRole('worker')->create(['farm_id' => $farm->id, 'user_id' => $user->id]);

        $migration = require database_path('migrations/2026_10_12_000002_rename_worker_role_to_accountant.php');
        $migration->up();

        $this->assertDatabaseMissing('roles', ['name' => 'worker']);
        $this->assertDatabaseHas('roles', ['name' => 'accountant', 'farm_id' => $farm->id]);

        setPermissionsTeamId($farm->id);
        $this->assertTrue($user->fresh()->hasRole('accountant'));

        $migration->down();
        $this->assertDatabaseHas('roles', ['name' => 'worker', 'farm_id' => $farm->id]);
    }

    public function test_existing_accountant_role_is_reused(): void
    {
        $farm = Farm::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        FarmUser::factory()->withRole('worker')->create(['farm_id' => $farm->id, 'user_id' => $a->id]);
        FarmUser::factory()->withRole('accountant')->create(['farm_id' => $farm->id, 'user_id' => $b->id]);

        (require database_path('migrations/2026_10_12_000002_rename_worker_role_to_accountant.php'))->up();

        $this->assertSame(1, DB::table('roles')->where('name', 'accountant')->where('farm_id', $farm->id)->count());
        setPermissionsTeamId($farm->id);
        $this->assertTrue($a->fresh()->hasRole('accountant'));
        $this->assertTrue($b->fresh()->hasRole('accountant'));
    }
}
