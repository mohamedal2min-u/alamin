<?php

namespace App\Console\Commands;

use App\Actions\Flock\CreateFlockAction;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Flock;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * حساب ومدجنة تجربة (لمراجعي Google Play وللعرض): مدير مدجنة على «مدجنة تجربة» مع فوج نشط.
 * كلمة المرور تُمرَّر مشفّرة (bcrypt) عبر stdin كي لا تظهر في سجلات التشغيل. إعادة التشغيل تحدّث كلمة المرور فقط.
 */
class CreateDemoAccount extends Command
{
    protected $signature = 'demo:create {--email=demo@alamin.se} {--hash= : للاختبارات فقط؛ في السيرفر تُمرَّر عبر stdin}';

    protected $description = 'إنشاء مدجنة تجربة ومدير لها (كلمة المرور مشفّرة bcrypt عبر stdin)';

    public function handle(): int
    {
        $hash = trim((string) ($this->option('hash') ?? stream_get_contents(STDIN)));
        if (! preg_match('/^\$2[aby]\$[0-9]{2}\$[.\/A-Za-z0-9]{53}$/', $hash)) {
            $this->error('كلمة المرور المشفّرة غير صالحة (المتوقع bcrypt عبر stdin).');

            return self::FAILURE;
        }
        $email = (string) $this->option('email');

        [$user, $farm, $flock] = DB::transaction(function () use ($email, $hash) {
            $user = User::where('email', $email)->first();
            if ($user) {
                // تجاوز cast «hashed»: القيمة مشفّرة مسبقاً
                DB::table('users')->where('id', $user->id)->update(['password' => $hash, 'status' => 'active']);
            } else {
                $user = User::create(['name' => 'حساب تجربة', 'email' => $email, 'password' => $hash, 'status' => 'active']);
            }

            $farm = Farm::firstOrCreate(
                ['name' => 'مدجنة تجربة'],
                ['location' => 'تجربة', 'status' => 'active', 'admin_user_id' => $user->id, 'started_at' => now()->toDateString(),
                    'created_by' => $user->id, 'updated_by' => $user->id]
            );

            FarmUser::firstOrCreate(
                ['farm_id' => $farm->id, 'user_id' => $user->id],
                ['status' => 'active', 'is_primary' => true, 'joined_at' => now(), 'created_by' => $user->id, 'updated_by' => $user->id]
            );

            $registrar = app(PermissionRegistrar::class);
            $registrar->setPermissionsTeamId($farm->id);
            if (! $user->hasRole('farm_admin')) {
                $user->assignRole('farm_admin');
            }
            $registrar->setPermissionsTeamId(null);

            $flock = Flock::where('farm_id', $farm->id)->first();
            if (! $flock) {
                $flock = app(CreateFlockAction::class)->execute($farm->id, $user->id, [
                    'name' => 'فوج تجربة',
                    'start_date' => now()->subDays(20)->toDateString(),
                    'initial_count' => 1000,
                ]);
                $flock->update(['status' => 'active']);
            }

            return [$user, $farm, $flock];
        });

        $this->info("تم: المستخدم {$user->email} (#{$user->id}) مدير «{$farm->name}» (#{$farm->id})، والفوج «{$flock->name}» ({$flock->status}).");

        return self::SUCCESS;
    }
}
