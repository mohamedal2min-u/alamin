<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'               => fake()->name(),
            'email'              => fake()->unique()->safeEmail(),
            'whatsapp'           => null,
            'email_verified_at'  => now(),
            'password'           => static::$password ??= Hash::make('password'),
            'status'             => 'active',
            'remember_token'     => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'inactive']);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'suspended']);
    }

    public function withWhatsapp(string $whatsapp = null): static
    {
        return $this->state(fn (array $attributes) => [
            'whatsapp' => $whatsapp ?? '966500000000',
            'email'    => null,
        ]);
    }
}
