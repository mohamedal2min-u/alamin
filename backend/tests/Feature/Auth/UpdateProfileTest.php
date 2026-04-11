<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_name_successfully(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/auth/me', ['name' => 'اسم جديد'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'اسم جديد')
            ->assertJsonPath('message', 'تم تحديث الملف الشخصي بنجاح');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'اسم جديد']);
    }

    public function test_update_email_successfully(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/auth/me', ['email' => 'new@example.com'])
            ->assertStatus(200)
            ->assertJsonPath('data.email', 'new@example.com');
    }

    public function test_update_email_to_null(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/auth/me', ['email' => null])
            ->assertStatus(200);

        $this->assertNull($user->fresh()->email);
    }

    public function test_update_email_duplicate_returns_422(): void
    {
        $other = User::factory()->create(['email' => 'taken@example.com']);
        $user  = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/auth/me', ['email' => 'taken@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_whatsapp_duplicate_returns_422(): void
    {
        User::factory()->withWhatsapp('966500000001')->create();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/auth/me', ['whatsapp' => '966500000001'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['whatsapp']);
    }

    public function test_update_same_email_on_own_account_passes(): void
    {
        $user = User::factory()->create(['email' => 'mine@example.com']);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/auth/me', ['email' => 'mine@example.com', 'name' => 'Updated'])
            ->assertStatus(200);
    }

    public function test_update_unauthenticated_returns_401(): void
    {
        $this->putJson('/api/auth/me', ['name' => 'test'])
            ->assertStatus(401);
    }

    public function test_update_empty_name_returns_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/auth/me', ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }
}
