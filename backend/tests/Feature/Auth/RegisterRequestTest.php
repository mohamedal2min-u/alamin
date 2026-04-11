<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterRequestTest extends TestCase
{
    use RefreshDatabase;

    private array $validPayload = [
        'name'                  => 'أحمد محمد',
        'whatsapp'              => '966512345678',
        'password'              => 'Secret123',
        'password_confirmation' => 'Secret123',
    ];

    public function test_register_request_created_successfully(): void
    {
        $this->postJson('/api/auth/register-request', $this->validPayload)
            ->assertStatus(201)
            ->assertJsonPath('message', 'تم إرسال طلب التسجيل بنجاح، سيتم التواصل معك قريباً');

        $this->assertDatabaseHas('registration_requests', [
            'name'     => 'أحمد محمد',
            'whatsapp' => '966512345678',
            'status'   => 'pending',
        ]);
    }

    public function test_register_request_with_optional_fields(): void
    {
        $payload = array_merge($this->validPayload, [
            'email'     => 'test@example.com',
            'location'  => 'الرياض',
            'farm_name' => 'مزرعة النور',
        ]);

        $this->postJson('/api/auth/register-request', $payload)
            ->assertStatus(201);

        $this->assertDatabaseHas('registration_requests', [
            'email'     => 'test@example.com',
            'location'  => 'الرياض',
            'farm_name' => 'مزرعة النور',
        ]);
    }

    public function test_missing_name_returns_422(): void
    {
        $payload = $this->validPayload;
        unset($payload['name']);

        $this->postJson('/api/auth/register-request', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_missing_whatsapp_returns_422(): void
    {
        $payload = $this->validPayload;
        unset($payload['whatsapp']);

        $this->postJson('/api/auth/register-request', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['whatsapp']);
    }

    public function test_password_not_confirmed_returns_422(): void
    {
        $payload = array_merge($this->validPayload, ['password_confirmation' => 'different']);

        $this->postJson('/api/auth/register-request', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_short_password_returns_422(): void
    {
        $payload = array_merge($this->validPayload, [
            'password'              => 'short',
            'password_confirmation' => 'short',
        ]);

        $this->postJson('/api/auth/register-request', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_invalid_email_format_returns_422(): void
    {
        $payload = array_merge($this->validPayload, ['email' => 'not-an-email']);

        $this->postJson('/api/auth/register-request', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_password_is_hashed_in_database(): void
    {
        $this->postJson('/api/auth/register-request', $this->validPayload);

        $record = \DB::table('registration_requests')->first();

        $this->assertNotEquals('Secret123', $record->password_hash);
        $this->assertTrue(\Hash::check('Secret123', $record->password_hash));
    }
}
