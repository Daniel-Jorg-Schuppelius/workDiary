<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScimGroupService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Scim;

use App\Models\{Organization, ScimGroup, Team, User};
use App\Services\SqidEncoder;

/**
 * Provisioning-Logik für den SCIM-2.0-Gruppenendpunkt (Feature 057, MVP-121 →
 * Rang 16).
 *
 * Eine Gruppe hält ihre IdP-Mitgliederliste selbst (Spalte `members`), damit GET
 * die Mitglieder auch ohne Team-Mapping vollständig zurückgibt (sonst löscht
 * Okta bei PUT alle Mitglieder). Die Mitgliedschaft wird **nur dann** nach
 * `team_user` projiziert, wenn die Gruppe explizit einem Team zugeordnet ist —
 * die Zuordnung ist ein bewusster Admin-Schritt ({@see mapToTeam}). SCIM vergibt
 * weiterhin **NIE Rollen**. Unbekannte Member-`value`-IDs werden tolerant
 * mitgeführt (`user_id = null`), damit Entra PATCHes nicht endlos wiederholt.
 */
class ScimGroupService {
    public const SCHEMA = 'urn:ietf:params:scim:schemas:core:2.0:Group';

    public function __construct(private readonly SqidEncoder $sqids) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, Organization $organization): ScimGroup {
        $displayName = $this->requireDisplayName($payload);
        $this->assertUniqueDisplayName($organization->id, $displayName, null);

        $group = new ScimGroup();
        $group->forceFill([
            'organization_id' => $organization->id,
            'display_name' => $displayName,
            'external_id' => $this->str($payload, 'externalId'),
            'members' => $this->normalizeMembers($organization->id, $this->rawMembers($payload['members'] ?? null)),
        ])->save();

        $this->projectToTeam($group, []);

        return $group->refresh();
    }

    /**
     * Vollständiges Ersetzen (PUT): displayName, externalId und die komplette
     * Mitgliederliste. Fehlende `members` bedeuten „leer" (PUT-Semantik).
     *
     * @param  array<string, mixed>  $payload
     */
    public function replace(ScimGroup $group, array $payload): ScimGroup {
        $displayName = $this->requireDisplayName($payload);
        $this->assertUniqueDisplayName($group->organization_id, $displayName, $group->id);

        $oldIds = $this->resolvedIds($group->members ?? []);
        $group->forceFill([
            'display_name' => $displayName,
            'members' => $this->normalizeMembers($group->organization_id, $this->rawMembers($payload['members'] ?? null)),
        ]);
        if (array_key_exists('externalId', $payload)) {
            $group->forceFill(['external_id' => $this->str($payload, 'externalId')]);
        }
        $group->save();

        $this->projectToTeam($group, $oldIds);

        return $group->refresh();
    }

    /**
     * Teilaktualisierung (PATCH, RFC 7644 §3.5.2) — muss für Entra UND Okta
     * funktionieren: `op` case-insensitiv, Member-remove in drei Formen
     * (`members[value eq "…"]`; `path:"members"` + value-Array; `path:"members"`
     * ohne value = alle), pfadloses `replace` mit Objekt-Value.
     *
     * @param  array<int, mixed>  $operations
     */
    public function applyPatch(ScimGroup $group, array $operations): ScimGroup {
        $oldIds = $this->resolvedIds($group->members ?? []);

        foreach ($operations as $op) {
            if (! is_array($op)) {
                continue;
            }
            $verb = strtolower(trim((string) ($op['op'] ?? '')));
            if (! in_array($verb, ['add', 'replace', 'remove'], true)) {
                continue;
            }
            $path = trim((string) ($op['path'] ?? ''));
            $value = $op['value'] ?? null;

            // Pfadloses replace/add mit Attribut-Objekt (Azure/Entra-Stil).
            if ($path === '' && is_array($value)) {
                $this->applyAttributes($group, $value);
                continue;
            }

            $this->applyPathOperation($group, $verb, $path, $value);
        }

        $group->save();
        $this->projectToTeam($group, $oldIds);

        return $group->refresh();
    }

    /**
     * Expliziter Admin-Schritt: Gruppe einem Team zuordnen (oder lösen). Beim
     * Zuordnen werden die aktuellen aufgelösten Mitglieder ins Team projiziert;
     * beim Lösen/Umhängen werden sie aus dem bisherigen Team entfernt.
     */
    public function mapToTeam(ScimGroup $group, ?Team $team): ScimGroup {
        $previousTeamId = $group->team_id;
        $memberIds = $this->resolvedIds($group->members ?? []);

        if ($previousTeamId !== null && $previousTeamId !== $team?->id) {
            $old = Team::query()->where('organization_id', $group->organization_id)->whereKey($previousTeamId)->first();
            if ($old instanceof Team && $memberIds !== []) {
                $old->members()->detach($memberIds);
            }
        }

        $group->forceFill(['team_id' => $team?->id])->save();
        $this->projectToTeam($group, []);

        return $group->refresh();
    }

    /**
     * SCIM-Group-Repräsentation (RFC 7644). `members` werden ausgelassen, wenn
     * `excludedAttributes=members` angefragt ist (so fragt Entra ab).
     *
     * @return array<string, mixed>
     */
    public function toResource(ScimGroup $group, bool $includeMembers = true): array {
        $resource = [
            'schemas' => [self::SCHEMA],
            'id' => $group->sqid,
            'displayName' => $group->display_name,
            'meta' => [
                'resourceType' => 'Group',
                'created' => $group->created_at?->toIso8601String(),
                'lastModified' => $group->updated_at?->toIso8601String(),
                'location' => url('/scim/v2/Groups/' . $group->sqid),
            ],
        ];

        if ($group->external_id !== null && $group->external_id !== '') {
            $resource['externalId'] = $group->external_id;
        }

        if ($includeMembers) {
            $resource['members'] = array_map(static fn (array $m): array => [
                'value' => $m['value'],
                '$ref' => url('/scim/v2/Users/' . $m['value']),
                'type' => 'User',
            ], $group->members ?? []);
        }

        return $resource;
    }

    /** Löscht die Gruppe (DELETE) und entfernt ihre Mitglieder aus dem gemappten Team. */
    public function delete(ScimGroup $group): void {
        if ($group->team_id !== null) {
            $team = Team::query()->where('organization_id', $group->organization_id)->whereKey($group->team_id)->first();
            $memberIds = $this->resolvedIds($group->members ?? []);
            if ($team instanceof Team && $memberIds !== []) {
                $team->members()->detach($memberIds);
            }
        }
        $group->delete();
    }

    // --- intern -----------------------------------------------------------

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function applyAttributes(ScimGroup $group, array $attributes): void {
        if (array_key_exists('displayName', $attributes)) {
            $group->forceFill(['display_name' => (string) $this->scalar($attributes['displayName'])]);
        }
        if (array_key_exists('externalId', $attributes)) {
            $group->forceFill(['external_id' => $this->str($attributes, 'externalId')]);
        }
        if (array_key_exists('members', $attributes)) {
            $group->members = $this->normalizeMembers($group->organization_id, $this->rawMembers($attributes['members']));
        }
    }

    private function applyPathOperation(ScimGroup $group, string $verb, string $path, mixed $value): void {
        $lc = strtolower($path);

        if ($lc === 'displayname') {
            $group->forceFill(['display_name' => (string) $this->scalar($value)]);

            return;
        }
        if ($lc === 'externalid') {
            $group->forceFill(['external_id' => $verb === 'remove' ? null : (string) $this->scalar($value)]);

            return;
        }
        if ($lc === 'members') {
            $this->applyMembersOperation($group, $verb, $value);

            return;
        }

        // remove-Form 1: Filterpfad members[value eq "…"].
        if ($verb === 'remove' && preg_match('/^members\[\s*value\s+eq\s+"?([^"\]]+)"?\s*\]$/i', $path, $m) === 1) {
            $this->setMembers($group, $this->filterOutValues($group->members ?? [], [trim($m[1])]));
        }
    }

    private function applyMembersOperation(ScimGroup $group, string $verb, mixed $value): void {
        if ($verb === 'add') {
            $add = $this->normalizeMembers($group->organization_id, $this->rawMembers($value));
            $this->setMembers($group, $this->mergeMembers($group->members ?? [], $add));

            return;
        }
        if ($verb === 'replace') {
            $this->setMembers($group, $this->normalizeMembers($group->organization_id, $this->rawMembers($value)));

            return;
        }
        // remove-Form 2/3: value-Array → diese entfernen; ohne value → alle.
        $values = $this->extractValues($this->rawMembers($value));
        $this->setMembers($group, $values === [] ? [] : $this->filterOutValues($group->members ?? [], $values));
    }

    /**
     * Projiziert die aufgelösten Mitglieder in das gemappte Team (Delta gegen
     * $oldIds). Ohne Team-Mapping passiert nichts.
     *
     * @param  list<int>  $oldIds
     */
    private function projectToTeam(ScimGroup $group, array $oldIds): void {
        if ($group->team_id === null) {
            return;
        }
        $team = Team::query()->where('organization_id', $group->organization_id)->whereKey($group->team_id)->first();
        if (! $team instanceof Team) {
            return;
        }

        $newIds = $this->resolvedIds($group->members ?? []);
        $add = array_values(array_diff($newIds, $oldIds));
        $remove = array_values(array_diff($oldIds, $newIds));

        if ($add !== []) {
            $team->members()->syncWithoutDetaching($add);
        }
        if ($remove !== []) {
            $team->members()->detach($remove);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $raw
     * @return list<array{value: string, user_id: int|null}>
     */
    private function normalizeMembers(int $organizationId, array $raw): array {
        $out = [];
        $seen = [];
        foreach ($this->extractValues($raw) as $value) {
            if (isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            $out[] = ['value' => $value, 'user_id' => $this->resolveValue($organizationId, $value)];
        }

        return $out;
    }

    /**
     * @param  list<array{value: string, user_id: int|null}>  $existing
     * @param  list<array{value: string, user_id: int|null}>  $add
     * @return list<array{value: string, user_id: int|null}>
     */
    private function mergeMembers(array $existing, array $add): array {
        $byValue = [];
        foreach ([...$existing, ...$add] as $member) {
            $byValue[$member['value']] = $member;
        }

        return array_values($byValue);
    }

    /**
     * @param  list<array{value: string, user_id: int|null}>  $members
     * @param  list<string>  $values
     * @return list<array{value: string, user_id: int|null}>
     */
    private function filterOutValues(array $members, array $values): array {
        return array_values(array_filter($members, static fn (array $m): bool => ! in_array($m['value'], $values, true)));
    }

    /**
     * @param  list<array{value: string, user_id: int|null}>  $members
     */
    private function setMembers(ScimGroup $group, array $members): void {
        $group->members = $members;
    }

    /**
     * @param  list<array{value: string, user_id: int|null}>  $members
     * @return list<int>
     */
    private function resolvedIds(array $members): array {
        $ids = [];
        foreach ($members as $member) {
            if ($member['user_id'] !== null) {
                $ids[] = (int) $member['user_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Normalisiert die vielfältigen SCIM-`members`-Formen zu einer Liste von
     * Member-Objekten (jeweils mit `value`).
     *
     * @return list<array<string, mixed>>
     */
    private function rawMembers(mixed $value): array {
        if (! is_array($value)) {
            return [];
        }
        // Einzelnes Member-Objekt ({value: …}) statt Liste.
        if (array_key_exists('value', $value)) {
            return [$value];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    /**
     * @param  list<array<string, mixed>>  $raw
     * @return list<string>
     */
    private function extractValues(array $raw): array {
        $out = [];
        foreach ($raw as $member) {
            $value = trim((string) ($member['value'] ?? ''));
            if ($value !== '') {
                $out[] = $value;
            }
        }

        return array_values(array_unique($out));
    }

    /** Löst eine SCIM-Member-`value` (User-Sqid) auf ein internes Konto der Org auf. */
    private function resolveValue(int $organizationId, string $value): ?int {
        $decoded = $this->sqids->decode(User::class, $value);
        if ($decoded === null) {
            return null;
        }
        $user = User::query()
            ->whereKey($decoded)
            ->where('organization_id', $organizationId)
            ->whereNull('customer_id')
            ->first();

        return $user instanceof User ? (int) $user->getKey() : null;
    }

    private function assertUniqueDisplayName(int $organizationId, string $displayName, ?int $ignoreId): void {
        $exists = ScimGroup::query()
            ->where('organization_id', $organizationId)
            ->where('display_name', $displayName)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
        if ($exists) {
            throw new ScimException(409, 'A group with this displayName already exists.', 'uniqueness');
        }
    }

    /** @param array<string, mixed> $payload */
    private function requireDisplayName(array $payload): string {
        $displayName = trim((string) ($payload['displayName'] ?? ''));
        if ($displayName === '') {
            throw new ScimException(400, 'displayName is required.', 'invalidValue');
        }

        return $displayName;
    }

    /** @param array<string, mixed> $payload */
    private function str(array $payload, string $key): ?string {
        $value = $payload[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    private function scalar(mixed $value): string {
        return is_scalar($value) ? (string) $value : '';
    }
}
