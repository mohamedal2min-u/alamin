<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_revokes_current_token(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'تم تسجيل الخروج بنجاح');

        // Token should be removed from database
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // مسح cache الـ auth لضمان إعادة التقييم من قاعدة البيانات
        $this->flushAuthBetweenRequests();

        // Token should no longer work
        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }

    public function test_logout_unauthenticated_returns_401(): void
    {
        $this->postJson('/api/auth/logout')
            ->assertStatus(401);
    }
}
