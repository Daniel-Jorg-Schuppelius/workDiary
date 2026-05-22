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

use App\Enums\User\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

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
            'organization_id' => Organization::factory(),
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
            self::syncRolesInOwnOrg($user, [UserRole::Admin->value]);
        });
    }

    public function user(): static {
        return $this->state(fn(array $attributes): array => [
            'name' => 'TestUser ' . Str::random(8),
            'email' => 'user-' . Str::random(10) . '@example.test',
        ])->afterCreating(function (User $user): void {
            self::syncRolesInOwnOrg($user, [UserRole::User->value]);
        });
    }

    public function callcenter(): static {
        return $this->afterCreating(function (User $user): void {
            self::syncRolesInOwnOrg($user, [UserRole::Callcenter->value]);
        });
    }

    public function buchhaltung(): static {
        return $this->afterCreating(function (User $user): void {
            self::syncRolesInOwnOrg($user, [UserRole::Buchhaltung->value]);
        });
    }

    public function geschaeftsfuehrung(): static {
        return $this->afterCreating(function (User $user): void {
            self::syncRolesInOwnOrg($user, [UserRole::Geschaeftsfuehrung->value]);
        });
    }

    public function teamleitung(): static {
        return $this->afterCreating(function (User $user): void {
            self::syncRolesInOwnOrg($user, [UserRole::Teamleitung->value]);
        });
    }

    public function aussendienst(): static {
        return $this->afterCreating(function (User $user): void {
            self::syncRolesInOwnOrg($user, [UserRole::Aussendienst->value]);
        });
    }

    public function support(): static {
        return $this->afterCreating(function (User $user): void {
            self::syncRolesInOwnOrg($user, [UserRole::Support->value]);
        });
    }

    /**
     * Portal-Account fuer Rolle `kunde`. Setzt customer_id und weist die
     * Rolle im Spatie-Team-Kontext der Organisation des Kunden zu.
     */
    public function kunde(int $customerId, ?int $organizationId = null): static {
        return $this->state(fn(array $attributes): array => [
            'customer_id' => $customerId,
            'organization_id' => $organizationId ?? ($attributes['organization_id'] ?? null),
        ])->afterCreating(function (User $user): void {
            self::syncRolesInOwnOrg($user, [UserRole::Kunde->value]);
        });
    }

    /**
     * Spatie-Teams wertet Rollen relativ zum aktiven `setPermissionsTeamId`
     * aus. In Tests bleibt dieser Kontext vom letzten Org-Create stehen –
     * und wenn der Factory-User per ['organization_id' => …] in eine
     * BESTEHENDE Org hereingehängt wird (typisch für Cross-Org-Tests),
     * läuft `syncRoles()` sonst gegen die falsche Team-ID. Hier setzen wir
     * den Kontext explizit auf die Org des Users, weisen die Rollen zu
     * und stellen den vorherigen Kontext wieder her.
     *
     * @param  list<string>  $roles
     */
    private static function syncRolesInOwnOrg(User $user, array $roles): void {
        if (empty($user->organization_id)) {
            $user->syncRoles($roles);

            return;
        }

        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        try {
            $registrar->setPermissionsTeamId((int) $user->organization_id);
            $user->syncRoles($roles);
        } finally {
            $registrar->setPermissionsTeamId($previous);
            // Wichtig in Test-Setups: Spatie cached die geladene Permission-
            // Map pro Container-Lifecycle. Ohne expliziten Reset würden
            // nachfolgende Role-Checks im selben Test den veralteten
            // (leeren) Stand für genau diesen User zurückgeben.
            $registrar->forgetCachedPermissions();
        }
    }
}
