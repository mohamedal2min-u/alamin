<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AddFarmUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_accountant_in_the_only_farm(): void
    {
        $farm = Farm::factory()->create();

        $this->artisan('user:add', ['email' => 'Sef@Alamin.se'])
            ->expectsQuestion('كلمة المرور (8 أحرف على الأقل)', 'secret-pass-1')
            ->assertExitCode(0);

        $user = User::where('email', 'sef@alamin.se')->firstOrFail();
        $this->assertTrue(Hash::check('secret-pass-1', $user->password));
        $this->assertDatabaseHas('farm_users', ['farm_id' => $farm->id, 'user_id' => $user->id, 'status' => 'active']);

        setPermissionsTeamId($farm->id);
        $this->assertTrue($user->fresh()->hasRole('accountant'));
    }

    public function test_refuses_when_there_are_several_farms_without_farm_option(): void
    {
        Farm::factory()->count(2)->create();

        $this->artisan('user:add', ['email' => 'sef@alamin.se'])->assertExitCode(1);
        $this->assertDatabaseMissing('users', ['email' => 'sef@alamin.se']);
    }

    public function test_rejects_short_password_and_bad_role(): void
    {
        Farm::factory()->create();

        $this->artisan('user:add', ['email' => 'sef@alamin.se'])
            ->expectsQuestion('كلمة المرور (8 أحرف على الأقل)', 'short')
            ->assertExitCode(1);

        $this->artisan('user:add', ['email' => 'sef@alamin.se', '--role' => 'partner'])->assertExitCode(1);
        $this->assertDatabaseMissing('users', ['email' => 'sef@alamin.se']);
    }

    public function test_existing_user_is_linked_without_touching_password(): void
    {
        $farm = Farm::factory()->create();
        $user = User::factory()->create(['email' => 'sef@alamin.se', 'password' => Hash::make('original-pass')]);

        $this->artisan('user:add', ['email' => 'sef@alamin.se'])->assertExitCode(0);

        $this->assertTrue(Hash::check('original-pass', $user->fresh()->password));
        $this->assertSame(1, FarmUser::where('farm_id', $farm->id)->where('user_id', $user->id)->count());
        setPermissionsTeamId($farm->id);
        $this->assertTrue($user->fresh()->hasRole('accountant'));
    }
}
