<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'type' => 1,
            'role_id' => null,
            'status' => 1,
            'login_off' => false,
        ];
    }

    /**
     * Superadmin: no role, full permission bypass.
     */
    public function superAdmin(): static
    {
        return $this->state(fn () => ['role_id' => null]);
    }

    /**
     * Attach a role granting the given free-text permission strings.
     *
     * @param  array<int, string>  $permissions
     */
    public function withRole(array $permissions, string $name = 'Test Role'): static
    {
        return $this->afterCreating(function (User $user) use ($permissions, $name) {
            $role = Role::create(['name' => $name, 'permissions' => $permissions]);
            $user->forceFill(['role_id' => (string) $role->id])->save();
        });
    }

    /**
     * A non-admin account (type != 1).
     */
    public function nonAdmin(): static
    {
        return $this->state(fn () => ['type' => 0]);
    }
}
