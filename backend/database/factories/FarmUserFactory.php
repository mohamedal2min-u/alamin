<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * @extends Factory<FarmUser>
 */
class FarmUserFactory extends Factory
{
    protected $model = FarmUser::class;

    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'user_id' => User::factory(),
            'status' => 'active',
            'is_primary' => true,
            'joined_at' => now(),
        ];
    }

    /**
     * كل عضو يُنشأ بدور farm_admin داخل مزرعته افتراضياً؛
     * استخدم ->withRole('worker') لاستبداله بدور آخر.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (FarmUser $farmUser): void {
            $this->assignRole($farmUser, 'farm_admin');
        });
    }

    public function withRole(string $role): static
    {
        return $this->afterCreating(function (FarmUser $farmUser) use ($role): void {
            $registrar = app(PermissionRegistrar::class);
            $previous = $registrar->getPermissionsTeamId();

            $registrar->setPermissionsTeamId($farmUser->farm_id);
            $farmUser->user->syncRoles([]);
            $registrar->setPermissionsTeamId($previous);

            $this->assignRole($farmUser, $role);
        });
    }

    private function assignRole(FarmUser $farmUser, string $role): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($farmUser->farm_id);
        Role::findOrCreate($role, 'web');
        $farmUser->user->assignRole($role);
        $registrar->setPermissionsTeamId($previous);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'inactive']);
    }
}
