<?php

namespace Tests\Feature\Notification;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** الإشعار يحمل تفاصيل التعديل (قبل ← بعد)، ويُرسل دفعاً برقمه، ويُفتح ويُعلَّم مقروءاً */
class EditNotificationDetailsTest extends TestCase
{
    use RefreshDatabase;

    private Farm $farm;

    private array $pushed = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->farm = Farm::factory()->create();
        $this->mock(PushNotificationService::class, function ($mock): void {
            $mock->shouldReceive('sendNotification')->andReturnUsing(function ($users, $title, $body, $data = [], $channel = 'default'): void {
                $this->pushed[] = ['ids' => collect($users instanceof User ? [$users] : $users)->pluck('id')->all(), 'body' => $body, 'data' => $data, 'channel' => $channel];
            });
        });
    }

    private function member(string $role): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->withRole($role)->create(['farm_id' => $this->farm->id, 'user_id' => $user->id, 'status' => 'active']);

        return $user;
    }

    private function as(User $u): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($u, 'sanctum')->withHeaders(['X-Farm-Id' => $this->farm->id]);
    }

    public function test_daily_entry_edit_notification_has_before_after_details(): void
    {
        $admin = $this->member('farm_admin');
        $sup = $this->member('supervisor');
        $flock = Flock::factory()->active()->create(['farm_id' => $this->farm->id, 'initial_count' => 1000]);

        $id = $this->as($sup)->postJson("/api/flocks/{$flock->id}/mortalities", ['entry_date' => now()->toDateString(), 'quantity' => 5])
            ->assertCreated()->json('data.id');
        $this->pushed = [];

        $this->as($sup)->patchJson("/api/daily-entries/mortality/{$id}", ['quantity' => 3, 'edit_reason' => 'خطأ إدخال'])->assertOk();

        $n = UserNotification::where('user_id', $admin->id)->latest('id')->firstOrFail();
        $this->assertSame($flock->name, $n->details['flock']);
        $this->assertSame("/flocks/{$flock->id}", $n->link);
        $this->assertContains(['label' => 'الكمية', 'from' => '5', 'to' => '3'], $n->details['rows']);
        $this->assertStringContainsString('الكمية: 5 ← 3', $n->body);

        $push = collect($this->pushed)->first(fn ($p) => $p['ids'] === [$admin->id]);
        $this->assertNotNull($push);
        $this->assertSame($n->id, $push['data']['notification_id']);

        // فتح الإشعار يعيد التفاصيل ويعلّمه مقروءاً
        $this->assertSame(1, $this->as($admin)->getJson('/api/auth/notifications')->assertOk()->json('unread'));
        $this->as($admin)->getJson("/api/auth/notifications/{$n->id}")
            ->assertOk()
            ->assertJsonPath('data.details.rows.0.label', 'الكمية')
            ->assertJsonPath('data.actor', $sup->name);
        $this->assertNotNull($n->fresh()->read_at);
        $listed = collect($this->as($admin)->getJson('/api/auth/notifications')->json('data'))->firstWhere('id', $n->id);
        $this->assertTrue($listed['read']);

        // لا يفتح إشعار مستخدم آخر
        $this->as($sup)->getJson("/api/auth/notifications/{$n->id}")->assertNotFound();
    }

    public function test_flock_update_shows_changed_fields_only(): void
    {
        $admin = $this->member('farm_admin');
        $acct = $this->member('accountant');
        $flock = Flock::factory()->active()->create(['farm_id' => $this->farm->id, 'name' => 'قديم', 'initial_count' => 1000]);

        $this->as($admin)->putJson("/api/flocks/{$flock->id}", ['name' => 'جديد', 'initial_count' => 1000])->assertOk();

        $n = UserNotification::where('user_id', $acct->id)->latest('id')->firstOrFail();
        $this->assertSame([['label' => 'الاسم', 'from' => 'قديم', 'to' => 'جديد']], $n->details['rows']);
    }
}
