<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScimUserService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Scim;

use App\Models\{ExternalReference, Organization, User};
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\DB;

/**
 * Provisioning-Logik für den SCIM-2.0-Benutzerendpunkt (Feature 057, MVP-121).
 *
 * SCIM ist **führend für Kontoexistenz und -status**, WorkDiary bleibt führend
 * für Rollen/Teams/fachliche Profile: dieser Service vergibt daher **niemals
 * Rollen** — insbesondere kann über SCIM keine (plattformweite) Admin-Rolle
 * entstehen (DoD 057). Deaktivierung (`active=false`) wirkt sofort: Sessions und
 * API-Tokens werden widerrufen, fachliche Daten bleiben erhalten.
 *
 * `externalId` (stabile IdP-Kennung) wird über {@see ExternalReference}
 * (Plugin `scim`, Typ `user`) geführt; die SCIM-`id` ist die WorkDiary-Sqid.
 */
class ScimUserService {
    public const PLUGIN_ID = 'scim';

    public const EXT_TYPE = 'user';

    private const SCHEMA = 'urn:ietf:params:scim:schemas:core:2.0:User';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, Organization $organization): User {
        $email = $this->requireUserName($payload);

        // users.email ist global unique → ein bereits vorhandenes Konto ist ein
        // SCIM-Uniqueness-Konflikt (der IdP soll per GET/Filter aktualisieren).
        if (User::query()->where('email', $email)->exists()) {
            throw new ScimException(409, 'A user with this userName already exists.', 'uniqueness');
        }

        // Vollaudit 2026-07 (H8): Lizenz-Nutzerlimit gilt auch für SCIM-Provisioning.
        try {
            app(\App\Services\Licensing\LimitGuard::class)->ensureCanCreateUser($organization);
        } catch (\App\Exceptions\LimitExceededException $e) {
            throw new ScimException(403, $e->getMessage());
        }

        $active = $this->activeFlag($payload, default: true);

        $user = new User();
        $user->forceFill([
            'organization_id' => $organization->id,
            'email' => $email,
            'name' => $this->displayName($payload, $email),
            'first_name' => $this->str($payload, 'name.givenName'),
            'last_name' => $this->str($payload, 'name.familyName'),
            'password' => Str::random(64), // unbenutzbar; Anmeldung später via SSO (MVP-120)
            'is_new_system' => true,
            'must_change_password' => false,
            'deactivated_at' => $active ? null : Carbon::now(),
        ])->save();

        $this->storeExternalId($user, $this->str($payload, 'externalId'));

        return $user->refresh();
    }

    /**
     * Vollständiges Ersetzen (PUT). Setzt Namen und Aktiv-Status; die E-Mail
     * (userName) bleibt der stabile Schlüssel und wird nicht umgezogen.
     *
     * @param  array<string, mixed>  $payload
     */
    public function replace(User $user, array $payload): User {
        $user->forceFill([
            'name' => $this->displayName($payload, (string) $user->email),
            'first_name' => $this->str($payload, 'name.givenName'),
            'last_name' => $this->str($payload, 'name.familyName'),
        ])->save();

        $this->setActive($user, $this->activeFlag($payload, default: ! $user->isDeactivated()));

        $externalId = $this->str($payload, 'externalId');
        if ($externalId !== null) {
            $this->storeExternalId($user, $externalId);
        }

        return $user->refresh();
    }

    /**
     * Teilaktualisierung (PATCH, RFC 7644 §3.5.2). Unterstützt den für die
     * De-/Reaktivierung entscheidenden `active`-Toggle sowie Namensfelder;
     * unbekannte Pfade werden ignoriert (kein harter Fehler).
     *
     * @param  array<int, mixed>  $operations
     */
    public function applyPatch(User $user, array $operations): User {
        foreach ($operations as $op) {
            if (! is_array($op)) {
                continue;
            }
            $verb = strtolower((string) ($op['op'] ?? ''));
            if (! in_array($verb, ['add', 'replace', 'remove'], true)) {
                continue;
            }
            $path = strtolower(trim((string) ($op['path'] ?? '')));
            $value = $op['value'] ?? null;

            // Kein Pfad → value ist ein Attribut-Objekt (Azure-Stil).
            if ($path === '' && is_array($value)) {
                $this->applyAttributes($user, $value);
                continue;
            }

            match ($path) {
                'active' => $this->setActive($user, $this->boolish($value)),
                'name.givenname' => $user->forceFill(['first_name' => $verb === 'remove' ? null : $this->scalar($value)])->save(),
                'name.familyname' => $user->forceFill(['last_name' => $verb === 'remove' ? null : $this->scalar($value)])->save(),
                'displayname' => $user->forceFill(['name' => (string) $this->scalar($value)])->save(),
                default => null,
            };
        }

        return $user->refresh();
    }

    /** Deaktivierung: sofort wirksam — Sessions + API-Tokens widerrufen, Daten bleiben. */
    public function deactivate(User $user): void {
        if (! $user->isDeactivated()) {
            $user->forceFill(['deactivated_at' => Carbon::now()])->save();
        }
        $this->revokeAccess($user);
    }

    public function reactivate(User $user): void {
        if ($user->isDeactivated()) {
            $user->forceFill(['deactivated_at' => null])->save();
        }
    }

    /**
     * SCIM-User-Repräsentation (RFC 7644).
     *
     * @return array<string, mixed>
     */
    public function toResource(User $user): array {
        $resource = [
            'schemas' => [self::SCHEMA],
            'id' => $user->sqid,
            'userName' => $user->email,
            'name' => [
                'givenName' => $user->first_name,
                'familyName' => $user->last_name,
                'formatted' => $user->name,
            ],
            'displayName' => $user->name,
            'emails' => [['value' => $user->email, 'primary' => true]],
            'active' => ! $user->isDeactivated(),
            'meta' => [
                'resourceType' => 'User',
                'created' => $user->created_at?->toIso8601String(),
                'lastModified' => $user->updated_at?->toIso8601String(),
                'location' => url('/scim/v2/Users/' . $user->sqid),
            ],
        ];

        $externalId = $this->externalIdFor($user);
        if ($externalId !== null) {
            $resource['externalId'] = $externalId;
        }

        return $resource;
    }

    // --- intern -----------------------------------------------------------

    /** @param array<string, mixed> $attributes */
    private function applyAttributes(User $user, array $attributes): void {
        if (array_key_exists('active', $attributes)) {
            $this->setActive($user, $this->boolish($attributes['active']));
        }
        if (array_key_exists('displayName', $attributes)) {
            $user->forceFill(['name' => (string) $this->scalar($attributes['displayName'])])->save();
        }
        if (isset($attributes['name']) && is_array($attributes['name'])) {
            $user->forceFill([
                'first_name' => $this->str($attributes, 'name.givenName') ?? $user->first_name,
                'last_name' => $this->str($attributes, 'name.familyName') ?? $user->last_name,
            ])->save();
        }
    }

    private function setActive(User $user, bool $active): void {
        if ($active) {
            $this->reactivate($user);
        } else {
            $this->deactivate($user);
        }
    }

    private function revokeAccess(User $user): void {
        $user->tokens()->delete(); // Sanctum
        DB::table('sessions')->where('user_id', $user->id)->delete(); // DB-Session-Driver
        $user->forceFill(['remember_token' => Str::random(60)])->save(); // „remember me" entwerten
    }

    private function storeExternalId(User $user, ?string $externalId): void {
        if ($externalId === null || $externalId === '') {
            // Trotzdem markieren, dass der Nutzer SCIM-provisioniert ist.
            $externalId = $user->sqid;
        }

        ExternalReference::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'plugin_id' => self::PLUGIN_ID,
                'external_type' => self::EXT_TYPE,
                'referenceable_type' => $user->getMorphClass(),
                'referenceable_id' => $user->getKey(),
            ],
            [
                'organization_id' => $user->organization_id,
                'external_id' => $externalId,
                'synced_at' => Carbon::now(),
            ],
        );
    }

    private function externalIdFor(User $user): ?string {
        $ref = ExternalReference::query()->withoutGlobalScopes()
            ->where('plugin_id', self::PLUGIN_ID)
            ->where('external_type', self::EXT_TYPE)
            ->forReferenceable($user)
            ->first();

        return $ref instanceof ExternalReference ? (string) $ref->external_id : null;
    }

    /** @param array<string, mixed> $payload */
    private function requireUserName(array $payload): string {
        $userName = trim((string) ($payload['userName'] ?? ''));
        if ($userName === '') {
            throw new ScimException(400, 'userName is required.', 'invalidValue');
        }

        return $userName;
    }

    /** @param array<string, mixed> $payload */
    private function displayName(array $payload, string $fallback): string {
        $display = trim((string) ($payload['displayName'] ?? ''));
        if ($display !== '') {
            return $display;
        }
        $given = (string) ($this->str($payload, 'name.givenName') ?? '');
        $family = (string) ($this->str($payload, 'name.familyName') ?? '');
        $full = trim($given . ' ' . $family);

        return $full !== '' ? $full : $fallback;
    }

    /** @param array<string, mixed> $payload */
    private function activeFlag(array $payload, bool $default): bool {
        return array_key_exists('active', $payload) ? $this->boolish($payload['active']) : $default;
    }

    /** @param array<string, mixed> $payload */
    private function str(array $payload, string $dotPath): ?string {
        $value = data_get($payload, $dotPath);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    private function scalar(mixed $value): string {
        return is_scalar($value) ? (string) $value : '';
    }

    private function boolish(mixed $value): bool {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }
}
