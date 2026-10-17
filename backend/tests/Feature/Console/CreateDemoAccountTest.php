<?php

namespace Tests\Feature\Console;

use App\Models\Farm;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CreateDemoAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('farm_admin', 'web');
    }

    public function test_creates_demo_farm_admin_and_active_flock_and_can_log_in(): void
    {
        $this->artisan('demo:create', ['--hash' => Hash::make('Demo-Pass-123')])->assertSuccessful();

        $user = User::where('email', 'demo@alamin.se')->firstOrFail();
        $farm = Farm::where('name', 'مدجنة تجربة')->firstOrFail();
        $this->assertTrue(Hash::check('Demo-Pass-123', $user->password));
        $this->assertSame('active', Flock::where('farm_id', $farm->id)->value('status'));

        app(PermissionRegistrar::class)->setPermissionsTeamId($farm->id);
        $this->assertTrue($user->fresh()->hasRole('farm_admin'));
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $this->postJson('/api/auth/login', ['login' => 'demo@alamin.se', 'password' => 'Demo-Pass-123', 'form_ms' => 3000])
            ->assertOk();
    }

    public function test_rerun_only_updates_password(): void
    {
        $this->artisan('demo:create', ['--hash' => Hash::make('first-pass-1')])->assertSuccessful();
        $this->artisan('demo:create', ['--hash' => Hash::make('second-pass-2')])->assertSuccessful();

        $this->assertSame(1, Farm::where('name', 'مدجنة تجربة')->count());
        $this->assertSame(1, Flock::count());
        $this->assertTrue(Hash::check('second-pass-2', User::where('email', 'demo@alamin.se')->value('password')));
    }

    public function test_rejects_plain_password(): void
    {
        $this->artisan('demo:create', ['--hash' => 'plain-password'])->assertFailed();
        $this->assertSame(0, User::where('email', 'demo@alamin.se')->count());
    }
}
