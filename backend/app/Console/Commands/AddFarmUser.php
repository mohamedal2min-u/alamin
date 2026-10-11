<?php

namespace App\Console\Commands;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * php artisan user:add sef@alamin.se --role=accountant
 *
 * ينشئ مستخدماً (أو يربط مستخدماً موجوداً) بمزرعة بدور محدد.
 * كلمة المرور تُطلب عند التشغيل ولا تُحفظ في أي ملف أو سجل أوامر.
 */
class AddFarmUser extends Command
{
    protected $signature = 'user:add
        {email : البريد الإلكتروني}
        {--role=accountant : farm_admin أو accountant}
        {--farm= : رقم المزرعة (اختياري إن كانت هناك مزرعة واحدة فقط)}
        {--name= : الاسم (الافتراضي: ما قبل @ في البريد)}';

    protected $description = 'إنشاء مستخدم وربطه بمزرعة بدور محدد';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $role = $this->option('role');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('البريد الإلكتروني غير صالح');

            return self::FAILURE;
        }

        if (! in_array($role, ['farm_admin', 'accountant'], true)) {
            $this->error('الدور يجب أن يكون farm_admin أو accountant');

            return self::FAILURE;
        }

        $farm = $this->resolveFarm();
        if (! $farm) {
            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        $password = null;

        if (! $user) {
            $password = $this->secret('كلمة المرور (8 أحرف على الأقل)');

            if (strlen((string) $password) < 8) {
                $this->error('كلمة المرور قصيرة (8 أحرف على الأقل)');

                return self::FAILURE;
            }
        }

        DB::transaction(function () use (&$user, $email, $role, $farm, $password): void {
            $user ??= User::create([
                'name' => $this->option('name') ?: explode('@', $email)[0],
                'email' => $email,
                'password' => Hash::make($password),
                'status' => 'active',
            ]);

            FarmUser::firstOrCreate(
                ['farm_id' => $farm->id, 'user_id' => $user->id],
                ['status' => 'active', 'is_primary' => ! FarmUser::where('user_id', $user->id)->exists(), 'joined_at' => now()]
            );
            FarmUser::where('farm_id', $farm->id)->where('user_id', $user->id)->update(['status' => 'active']);

            $registrar = app(PermissionRegistrar::class);
            $previous = $registrar->getPermissionsTeamId();
            $registrar->setPermissionsTeamId($farm->id);

            Role::findOrCreate($role, 'web');
            $user->unsetRelation('roles');
            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }

            $registrar->setPermissionsTeamId($previous);
        });

        $this->info("تم: {$email} صار {$role} في المزرعة «{$farm->name}» (#{$farm->id}).");

        return self::SUCCESS;
    }

    private function resolveFarm(): ?Farm
    {
        if ($id = $this->option('farm')) {
            $farm = Farm::find($id);
            if (! $farm) {
                $this->error("المزرعة #{$id} غير موجودة");
            }

            return $farm;
        }

        $farms = Farm::all();

        if ($farms->count() !== 1) {
            $this->error('يوجد '.$farms->count().' مزارع؛ حدّد المزرعة بـ --farm=ID');
            $farms->each(fn (Farm $f) => $this->line("  #{$f->id} {$f->name}"));

            return null;
        }

        return $farms->first();
    }
}
