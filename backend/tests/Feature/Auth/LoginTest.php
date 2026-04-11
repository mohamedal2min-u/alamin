<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    // ── تسجيل الدخول ─────────────────────────────────────────────────────────

    public function test_login_with_email_returns_token_and_user(): void
    {
        $user = User::factory()->create([
            'email'    => 'test@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login'    => 'test@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'status', 'farms'],
            ])
            ->assertJsonPath('user.email', 'test@example.com');
    }

    public function test_login_with_whatsapp_returns_token_and_user(): void
    {
        User::factory()->withWhatsapp('966512345678')->create([
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login'    => '966512345678',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_login_updates_last_login_at(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $this->assertNull($user->last_login_at);

        $this->postJson('/api/auth/login', [
            'login'    => $user->email,
            'password' => 'secret123',
        ]);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_login_wrong_password_returns_401(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/auth/login', [
            'login'    => $user->email,
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'بيانات الدخول غير صحيحة');
    }

    public function test_login_nonexistent_user_returns_401(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'login'    => 'nobody@example.com',
            'password' => 'anypassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_inactive_user_returns_403(): void
    {
        $user = User::factory()->inactive()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/auth/login', [
            'login'    => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'هذا الحساب غير نشط');
    }

    public function test_login_suspended_user_returns_403(): void
    {
        $user = User::factory()->suspended()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/auth/login', [
            'login'    => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'هذا الحساب موقوف، يرجى التواصل مع المسؤول');
    }

    public function test_login_missing_login_field_returns_422(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'password' => 'secret123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['login']);
    }

    public function test_login_missing_password_field_returns_422(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'login' => 'test@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}
