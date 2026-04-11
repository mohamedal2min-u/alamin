<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_password_successfully(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPass123')]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/auth/password', [
                'current_password'          => 'OldPass123',
                'password'                  => 'NewPass456',
                'password_confirmation'     => 'NewPass456',
            ])
            ->assertStatus(200)
            ->assertJsonPath('message', 'تم تغيير كلمة المرور بنجاح');

        $this->assertTrue(Hash::check('NewPass456', $user->fresh()->password));
    }

    public function test_change_password_revokes_other_tokens(): void
    {
        $user         = User::factory()->create(['password' => Hash::make('OldPass123')]);
        $otherToken   = $user->createToken('other-device')->plainTextToken;
        $currentToken = $user->createToken('current-device')->plainTextToken;

        $this->withToken($currentToken)
            ->putJson('/api/auth/password', [
                'current_password'      => 'OldPass123',
                'password'              => 'NewPass456',
                'password_confirmation' => 'NewPass456',
            ])
            ->assertStatus(200);

        // مسح cache الـ auth لضمان إعادة التقييم من قاعدة البيانات
        $this->flushAuthBetweenRequests();

        // Other token should be revoked
        $this->withToken($otherToken)
            ->getJson('/api/auth/me')
            ->assertStatus(401);

        $this->flushAuthBetweenRequests();

        // Current token remains valid
        $this->withToken($currentToken)
            ->getJson('/api/auth/me')
            ->assertStatus(200);
    }

    public function test_wrong_current_password_returns_422(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPass123')]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/auth/password', [
                'current_password'      => 'WrongPassword',
                'password'              => 'NewPass456',
                'password_confirmation' => 'NewPass456',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_password_too_short_returns_422(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPass123')]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/auth/password', [
                'current_password'      => 'OldPass123',
                'password'              => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_confirmation_mismatch_returns_422(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPass123')]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/auth/password', [
                'current_password'      => 'OldPass123',
                'password'              => 'NewPass456',
                'password_confirmation' => 'Different456',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_change_password_unauthenticated_returns_401(): void
    {
        $this->putJson('/api/auth/password', [
            'current_password'      => 'OldPass123',
            'password'              => 'NewPass456',
            'password_confirmation' => 'NewPass456',
        ])->assertStatus(401);
    }
}
