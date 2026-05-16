<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory {
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'organization_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static {
        return $this->afterCreating(function (User $user): void {
            $user->syncRoles([User::ROLE_ADMIN]);
        });
    }

    public function user(): static {
        return $this->state(fn(array $attributes): array => [
            'name' => 'TestUser ' . Str::random(8),
            'email' => 'user-' . Str::random(10) . '@example.test',
        ])->afterCreating(function (User $user): void {
            $user->syncRoles([User::ROLE_USER]);
        });
    }

    public function callcenter(): static {
        return $this->afterCreating(function (User $user): void {
            $user->syncRoles([User::ROLE_CALLCENTER]);
        });
    }
}
