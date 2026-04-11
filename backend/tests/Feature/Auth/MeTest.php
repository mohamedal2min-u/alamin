<?php

namespace Tests\Feature\Auth;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_authenticated_user_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'status', 'farms'],
            ])
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_me_returns_farms_for_user_with_membership(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create();

        FarmUser::create([
            'farm_id'    => $farm->id,
            'user_id'    => $user->id,
            'status'     => 'active',
            'is_primary' => true,
        ]);

        // Assign role in farm context
        setPermissionsTeamId($farm->id);
        Role::firstOrCreate(['name' => 'farm_admin', 'guard_name' => 'web']);
        $user->assignRole('farm_admin');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me');

        $response->assertStatus(200);
        $farms = $response->json('data.farms');

        $this->assertCount(1, $farms);
        $this->assertEquals($farm->id, $farms[0]['id']);
        $this->assertEquals('farm_admin', $farms[0]['role']);
    }

    public function test_me_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/auth/me')
            ->assertStatus(401);
    }
}
