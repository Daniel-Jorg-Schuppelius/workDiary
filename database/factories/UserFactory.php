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
use App\Enums\User\UserRole;
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
            'organization_id' => \App\Models\Organization::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Factory-User gehören standardmäßig zum neuen System.
            // Für Legacy-Schattenaccounts gibt es die State-Methode legacyOnly().
            'is_new_system' => true,
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

    /**
     * Schatten-Account: existiert nur im Legacy-System, kein Zugriff auf neue Funktionen.
     */
    public function legacyOnly(int $legacyUserId = 999): static {
        return $this->state(fn(array $attributes): array => [
            'legacy_user_id' => $legacyUserId,
            'is_new_system' => false,
        ]);
    }

    public function admin(): static {
        return $this->afterCreating(function (User $user): void {
            $user->syncRoles([UserRole::Admin->value]);
        });
    }

    public function user(): static {
        return $this->state(fn(array $attributes): array => [
            'name' => 'TestUser ' . Str::random(8),
            'email' => 'user-' . Str::random(10) . '@example.test',
        ])->afterCreating(function (User $user): void {
            $user->syncRoles([UserRole::User->value]);
        });
    }

    public function callcenter(): static {
        return $this->afterCreating(function (User $user): void {
            $user->syncRoles([UserRole::Callcenter->value]);
        });
    }
}
