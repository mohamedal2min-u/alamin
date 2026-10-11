<?php

namespace Tests\Feature\Partner;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Partner;
use App\Models\PartnerShareAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerShareTest extends TestCase
{
    use RefreshDatabase;

    private Farm $farm;

    private User $accountant;

    private User $admin;

    private Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->farm = Farm::factory()->create();
        $this->admin = $this->member('farm_admin');
        $this->accountant = $this->member('accountant');
        $this->farm->update(['admin_user_id' => $this->admin->id]);

        $this->partner = Partner::create([
            'farm_id' => $this->farm->id, 'name' => 'شريك أ', 'whatsapp' => '0999', 'status' => 'active',
        ]);
    }

    private function member(string $role): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->withRole($role)->create([
            'farm_id' => $this->farm->id, 'user_id' => $user->id, 'status' => 'active',
        ]);

        return $user;
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'sanctum')->withHeaders(['X-Farm-Id' => $this->farm->id]);
    }

    public function test_accountant_sees_partners_and_sets_a_share(): void
    {
        $this->as($this->accountant)->getJson('/api/partners')->assertOk();

        $this->as($this->accountant)
            ->patchJson("/api/partners/{$this->partner->id}/share", ['share_percent' => 30])
            ->assertOk()
            ->assertJsonPath('data.share_percent', 30);

        // حصة المدير تُحسب تلقائياً
        $list = $this->as($this->accountant)->getJson('/api/partners')->assertOk()->json('data');
        $this->assertEquals(70, collect($list)->firstWhere('is_manager', true)['share_percent']);

        $this->assertDatabaseHas('partner_share_audit_logs', [
            'partner_id' => $this->partner->id, 'user_id' => $this->accountant->id, 'old_percent' => null, 'new_percent' => 30,
        ]);
    }

    public function test_changing_a_share_again_keeps_old_value_in_the_audit_log(): void
    {
        $this->as($this->accountant)->patchJson("/api/partners/{$this->partner->id}/share", ['share_percent' => 30])->assertOk();
        $this->as($this->accountant)->patchJson("/api/partners/{$this->partner->id}/share", ['share_percent' => 40])->assertOk();

        $this->assertDatabaseHas('partner_share_audit_logs', ['old_percent' => 30, 'new_percent' => 40]);

        $this->as($this->admin)->getJson('/api/accounting/share-audit')
            ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.old_percent', 30);

        $this->as($this->accountant)->getJson('/api/accounting/share-audit')->assertStatus(403);
    }

    public function test_share_cannot_exceed_the_managers_remaining_share(): void
    {
        $this->as($this->accountant)->patchJson("/api/partners/{$this->partner->id}/share", ['share_percent' => 101])->assertStatus(422);
        $this->assertSame(0, PartnerShareAuditLog::count());
    }

    public function test_accountant_cannot_use_the_full_partner_update_or_delete(): void
    {
        $payload = ['name' => 'x', 'whatsapp' => '1', 'status' => 'active', 'password' => 'new-password-1'];

        $this->as($this->accountant)->putJson("/api/partners/{$this->partner->id}", $payload)->assertStatus(403);
        $this->as($this->accountant)->deleteJson("/api/partners/{$this->partner->id}")->assertStatus(403);
        $this->as($this->accountant)->postJson('/api/partners', $payload)->assertStatus(403);
    }

    public function test_partner_role_cannot_set_shares(): void
    {
        $partnerUser = $this->member('partner');

        $this->as($partnerUser)->patchJson("/api/partners/{$this->partner->id}/share", ['share_percent' => 10])->assertStatus(403);
    }
}
