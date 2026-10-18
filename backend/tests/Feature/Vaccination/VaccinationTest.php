<?php

namespace Tests\Feature\Vaccination;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\FlockVaccination;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Warehouse;
use App\Models\WarehouseItem;
use App\Services\PushNotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaccinationTest extends TestCase
{
    use RefreshDatabase;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->farm = Farm::factory()->create(['status' => 'active']);
    }

    private function member(string $role, ?Farm $farm = null, string $status = 'active'): User
    {
        $user = User::factory()->create();
        FarmUser::factory()->withRole($role)->create([
            'farm_id' => ($farm ?? $this->farm)->id, 'user_id' => $user->id, 'status' => $status,
        ]);

        return $user;
    }

    private function as(User $u, ?Farm $farm = null): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($u, 'sanctum')->withHeaders(['X-Farm-Id' => ($farm ?? $this->farm)->id]);
    }

    private function activeFlock(string $start, ?Farm $farm = null): Flock
    {
        return Flock::factory()->create([
            'farm_id' => ($farm ?? $this->farm)->id, 'status' => 'active', 'start_date' => $start,
        ]);
    }

    public function test_default_template_is_seeded_lazily_as_example(): void
    {
        $admin = $this->member('farm_admin');

        $res = $this->as($admin)->getJson('/api/vaccine-template')->assertOk();

        $res->assertJsonPath('data.is_example', true);
        $this->assertStringContainsString('مثال', $res->json('data.name'));
        $this->assertSame([1, 7, 12, 18, 24], array_column($res->json('data.items'), 'age_day'));

        // لا يُكرَّر عند الوصول الثاني
        $this->as($admin)->getJson('/api/vaccine-template')->assertOk();
        $this->assertDatabaseCount('vaccine_schedule_templates', 1);
    }

    public function test_activating_flock_generates_schedule_with_age_convention(): void
    {
        $admin = $this->member('farm_admin');
        $flock = Flock::factory()->draft()->create(['farm_id' => $this->farm->id, 'start_date' => '2026-10-01']);

        $this->as($admin)->putJson("/api/flocks/{$flock->id}", ['status' => 'active'])->assertOk();

        $rows = FlockVaccination::where('flock_id', $flock->id)->orderBy('age_day')->get();
        $this->assertCount(5, $rows);
        // اليوم الأول من العمر = تاريخ البدء (current_age_days = diff + 1)
        $this->assertSame('2026-10-01', $rows[0]->due_date->toDateString());
        $this->assertSame('2026-10-07', $rows[1]->due_date->toDateString());
        $this->assertSame('2026-10-12', $rows[2]->due_date->toDateString());
        $this->assertSame('2026-10-24', $rows[4]->due_date->toDateString());
        $this->assertTrue($rows->every(fn ($r) => $r->status === 'due'));
    }

    public function test_existing_active_flock_gets_schedule_on_first_access_once(): void
    {
        $partner = $this->member('partner');
        $flock = $this->activeFlock('2026-10-10');

        $this->as($partner)->getJson("/api/flocks/{$flock->id}/vaccinations")
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.1.due_date', '2026-10-16');

        $this->as($partner)->getJson('/api/vaccinations/active')->assertOk()
            ->assertJsonPath('flock.id', $flock->id)
            ->assertJsonCount(5, 'data');

        $this->assertSame(5, FlockVaccination::where('flock_id', $flock->id)->count());
    }

    public function test_done_and_skip_allowed_for_manager_and_supervisor_only(): void
    {
        $flock = $this->activeFlock(now()->subDays(6)->toDateString());
        $admin = $this->member('farm_admin');
        $this->as($admin)->getJson("/api/flocks/{$flock->id}/vaccinations")->assertOk();
        [$v1, $v2, $v3] = FlockVaccination::where('flock_id', $flock->id)->orderBy('age_day')->get();

        $supervisor = $this->member('supervisor');
        $this->as($supervisor)->patchJson("/api/flocks/{$flock->id}/vaccinations/{$v1->id}", ['status' => 'done', 'note' => 'دفعة 123'])
            ->assertOk()->assertJsonPath('data.status', 'done')->assertJsonPath('data.done_by.id', $supervisor->id);

        $this->as($admin)->patchJson("/api/flocks/{$flock->id}/vaccinations/{$v2->id}", ['status' => 'skipped', 'note' => 'بأمر الطبيب'])
            ->assertOk()->assertJsonPath('data.status', 'skipped');

        foreach (['accountant', 'partner'] as $role) {
            $u = $this->member($role);
            $this->as($u)->patchJson("/api/flocks/{$flock->id}/vaccinations/{$v3->id}", ['status' => 'done'])->assertStatus(403);
        }
        $this->assertSame('due', $v3->fresh()->status);

        $v1->refresh();
        $this->assertNotNull($v1->done_at);
        $this->assertSame('دفعة 123', $v1->note);

        // إعادة إلى «مستحق» تمسح التنفيذ
        $this->as($admin)->patchJson("/api/flocks/{$flock->id}/vaccinations/{$v1->id}", ['status' => 'due'])->assertOk();
        $this->assertNull($v1->fresh()->done_at);
    }

    public function test_template_edit_is_admin_only_and_apply_keeps_done_rows(): void
    {
        $flock = $this->activeFlock('2026-10-01');
        $admin = $this->member('farm_admin');
        $supervisor = $this->member('supervisor');
        $this->as($admin)->getJson("/api/flocks/{$flock->id}/vaccinations")->assertOk();
        $day7 = FlockVaccination::where('flock_id', $flock->id)->where('age_day', 7)->first();
        $this->as($admin)->patchJson("/api/flocks/{$flock->id}/vaccinations/{$day7->id}", ['status' => 'done'])->assertOk();

        $payload = ['name' => 'برنامج الطبيب', 'items' => [
            ['age_day' => 7, 'title' => $day7->title, 'method' => 'drinking_water'],
            ['age_day' => 10, 'title' => 'غامبورو', 'method' => 'drinking_water', 'withdrawal_days' => 0],
            ['age_day' => 20, 'title' => 'نيوكاسل', 'method' => 'eye_drop', 'notes' => 'قطرة عين'],
        ]];

        $this->as($supervisor)->putJson('/api/vaccine-template', $payload)->assertStatus(403);
        $this->as($admin)->putJson('/api/vaccine-template', $payload)->assertOk()
            ->assertJsonPath('data.is_example', false)
            ->assertJsonCount(3, 'data.items');

        // تعديل القالب لا يعيد كتابة الفوج الحالي
        $this->assertSame(5, FlockVaccination::where('flock_id', $flock->id)->count());

        $this->as($supervisor)->postJson('/api/vaccine-template/apply-active')->assertStatus(403);
        $this->as($admin)->postJson('/api/vaccine-template/apply-active')->assertOk()->assertJsonPath('data.created', 2);

        $rows = FlockVaccination::where('flock_id', $flock->id)->orderBy('age_day')->get();
        $this->assertSame([7, 10, 20], $rows->pluck('age_day')->all());
        $this->assertSame($day7->id, $rows[0]->id);
        $this->assertSame('done', $rows[0]->status);
        $this->assertSame('2026-10-10', $rows[1]->due_date->toDateString());
    }

    public function test_invalid_method_rejected(): void
    {
        $admin = $this->member('farm_admin');
        $this->as($admin)->putJson('/api/vaccine-template', ['name' => 'س', 'items' => [
            ['age_day' => 7, 'title' => 'نيوكاسل', 'method' => 'pill'],
        ]])->assertStatus(422);
    }

    public function test_no_sale_before_is_max_of_medicines_and_done_vaccinations(): void
    {
        $flock = $this->activeFlock(now()->subDays(20)->toDateString());
        $admin = $this->member('farm_admin');
        $medType = ItemType::factory()->medicine()->create();
        $antibiotic = Item::factory()->forFarm($this->farm)->create(['item_type_id' => $medType->id, 'unit_value' => 1, 'withdrawal_days' => 5]);
        $vitamin = Item::factory()->forFarm($this->farm)->create(['item_type_id' => $medType->id, 'unit_value' => 1, 'withdrawal_days' => null]);
        $warehouse = Warehouse::where('farm_id', $this->farm->id)->first();
        foreach ([$antibiotic, $vitamin] as $item) {
            WarehouseItem::factory()->create(['farm_id' => $this->farm->id, 'warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'current_quantity' => 100]);
        }

        $this->as($admin)->getJson("/api/flocks/{$flock->id}/withdrawal")->assertOk()->assertJsonPath('data.no_sale_before', null);

        $d1 = now()->subDays(4)->toDateString();
        $d2 = now()->subDays(2)->toDateString();
        $this->as($admin)->postJson("/api/flocks/{$flock->id}/medicine-logs", ['item_id' => $antibiotic->id, 'quantity' => 1, 'entry_date' => $d1])->assertCreated();
        $this->as($admin)->postJson("/api/flocks/{$flock->id}/medicine-logs", ['item_id' => $antibiotic->id, 'quantity' => 1, 'entry_date' => $d2])
            ->assertCreated()
            ->assertJsonPath('withdrawal.no_sale_before', now()->subDays(2)->addDays(5)->toDateString());
        // دواء بلا فترة سحب لا يؤثر
        $this->as($admin)->postJson("/api/flocks/{$flock->id}/medicine-logs", ['item_id' => $vitamin->id, 'quantity' => 1])->assertCreated();

        $this->as($admin)->getJson("/api/flocks/{$flock->id}")->assertOk()
            ->assertJsonPath('data.no_sale_before', now()->addDays(3)->toDateString());

        // لقاح منفَّذ بفترة سحب أطول يصبح هو المرجع
        $this->as($admin)->getJson("/api/flocks/{$flock->id}/vaccinations")->assertOk();
        $v = FlockVaccination::where('flock_id', $flock->id)->orderBy('age_day')->first();
        $v->update(['withdrawal_days' => 10]);
        $this->as($admin)->patchJson("/api/flocks/{$flock->id}/vaccinations/{$v->id}", ['status' => 'done'])->assertOk()
            ->assertJsonPath('withdrawal.no_sale_before', now()->addDays(10)->toDateString())
            ->assertJsonPath('withdrawal.source', $v->title);

        // اللقاح المتخطّى لا يُحسب
        $this->as($admin)->patchJson("/api/flocks/{$flock->id}/vaccinations/{$v->id}", ['status' => 'skipped'])->assertOk()
            ->assertJsonPath('withdrawal.no_sale_before', now()->addDays(3)->toDateString());
    }

    public function test_medicine_item_withdrawal_days_can_be_saved(): void
    {
        $admin = $this->member('farm_admin');
        $medType = ItemType::factory()->medicine()->create();
        $item = Item::factory()->forFarm($this->farm)->create(['item_type_id' => $medType->id]);

        $this->as($admin)->putJson("/api/inventory/items/{$item->id}", [
            'name' => $item->name, 'input_unit' => 'علبة', 'unit_value' => 1, 'content_unit' => 'غرام', 'withdrawal_days' => 7,
        ])->assertOk();
        $this->assertSame(7, $item->fresh()->withdrawal_days);

        $this->as($admin)->getJson('/api/inventory/items?type=medicine')->assertOk()->assertJsonPath('data.0.withdrawal_days', 7);
    }

    public function test_other_farm_cannot_read_or_mark_vaccinations(): void
    {
        $flock = $this->activeFlock(now()->subDays(3)->toDateString());
        $admin = $this->member('farm_admin');
        $this->as($admin)->getJson("/api/flocks/{$flock->id}/vaccinations")->assertOk();
        $v = FlockVaccination::where('flock_id', $flock->id)->first();

        $otherFarm = Farm::factory()->create(['status' => 'active']);
        $outsider = $this->member('farm_admin', $otherFarm);

        $this->as($outsider, $otherFarm)->getJson("/api/flocks/{$flock->id}/vaccinations")->assertNotFound();
        $this->as($outsider, $otherFarm)->getJson("/api/flocks/{$flock->id}/withdrawal")->assertNotFound();
        $this->as($outsider, $otherFarm)->patchJson("/api/flocks/{$flock->id}/vaccinations/{$v->id}", ['status' => 'done'])->assertNotFound();
        $this->assertSame('due', $v->fresh()->status);

        // قالب المزرعة الأخرى منفصل، وتطبيقه لا يمسّ فوج هذه المزرعة
        $this->as($outsider, $otherFarm)->putJson('/api/vaccine-template', ['name' => 'آخر', 'items' => []])->assertOk();
        $this->as($outsider, $otherFarm)->postJson('/api/vaccine-template/apply-active')->assertStatus(422);
        $this->as($admin)->getJson('/api/vaccine-template')->assertJsonCount(5, 'data.items');
        $this->assertSame(5, FlockVaccination::where('flock_id', $flock->id)->count());
        $this->as($outsider, $otherFarm)->getJson('/api/vaccinations/active')->assertOk()->assertJsonPath('flock', null);
    }

    public function test_remind_command_notifies_active_members_of_farms_due_today(): void
    {
        $today = Carbon::now('Asia/Damascus')->toDateString();

        // مزرعة 1: لقاح اليوم السابع مستحق اليوم
        $flockA = $this->activeFlock(Carbon::parse($today)->subDays(6)->toDateString());
        $adminA = $this->member('farm_admin');
        $accountantA = $this->member('accountant');
        $inactiveA = $this->member('supervisor', null, 'inactive');

        // مزرعة 2: لا شيء مستحق اليوم (عمر 3 أيام)
        $farmB = Farm::factory()->create(['status' => 'active']);
        $this->activeFlock(Carbon::parse($today)->subDays(2)->toDateString(), $farmB);
        $adminB = $this->member('farm_admin', $farmB);

        // مزرعة 3: لقاح اليوم لكنه منفَّذ مسبقاً
        $farmC = Farm::factory()->create(['status' => 'active']);
        $flockC = $this->activeFlock(Carbon::parse($today)->subDays(6)->toDateString(), $farmC);
        $adminC = $this->member('farm_admin', $farmC);
        app(\App\Services\VaccinationService::class)->generateIfMissing($flockC);
        FlockVaccination::where('flock_id', $flockC->id)->whereDate('due_date', $today)->update(['status' => 'done', 'done_at' => now()]);

        $pushed = [];
        $this->mock(PushNotificationService::class, function ($mock) use (&$pushed): void {
            $mock->shouldReceive('sendNotification')->andReturnUsing(function ($users, $title, $body, $data, $channel) use (&$pushed): void {
                $pushed[] = ['ids' => collect($users)->pluck('id')->sort()->values()->all(), 'title' => $title, 'body' => $body, 'channel' => $channel];
            });
        });

        $this->artisan('vaccines:remind')->assertSuccessful();

        $this->assertCount(1, $pushed);
        $this->assertSame(collect([$adminA->id, $accountantA->id])->sort()->values()->all(), $pushed[0]['ids']);
        $this->assertSame('manager_edit', $pushed[0]['channel']);
        $this->assertStringContainsString('نيوكاسل', $pushed[0]['body']);
        $this->assertStringContainsString($flockA->name, $pushed[0]['title']);

        $this->assertSame(1, UserNotification::where('user_id', $adminA->id)->where('kind', 'manager_edit')->count());
        $this->assertSame(0, UserNotification::where('user_id', $inactiveA->id)->count());
        $this->assertSame(0, UserNotification::whereIn('user_id', [$adminB->id, $adminC->id])->count());
    }
}
