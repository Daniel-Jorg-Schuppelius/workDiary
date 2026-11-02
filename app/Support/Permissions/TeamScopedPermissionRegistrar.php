<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TeamScopedPermissionRegistrar.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Permissions;

use Illuminate\Database\Eloquent\{Collection, Model};
use Spatie\Permission\PermissionRegistrar;

/**
 * Rechte-Cache je Team (Leistungsanalyse 2026-11-02, perf-14).
 *
 * Spatie hält einen Cache für alle Teams und hydriert je Request jedes
 * Recht mit den Rollen aller Mandanten (4 015 Zeilen ≈ 0,25 s); die
 * Org-Middleware leerte ihn obendrein bei jedem Request. Hier bekommt jede
 * Organisation ihren eigenen Schlüssel mit nur ihren und den globalen
 * Rollen; ein Teamwechsel wechselt den Schlüssel, eine Rechteänderung hebt
 * eine Versionsnummer an, die alle Schlüssel entwertet — der Datenbank-
 * Cache kennt keine Tags.
 */
class TeamScopedPermissionRegistrar extends PermissionRegistrar {
    private string $baseCacheKey = '';

    private int|string|null $scopedTeamId = null;

    /** Version je Instanz einmal gelesen — Spatie fragt `getPermissions()` je Rechteprüfung. */
    private ?int $versionMemo = null;

    public function initializeCache(): void {
        parent::initializeCache();
        $this->baseCacheKey = $this->cacheKey;
        $this->scopedTeamId = $this->teams ? $this->getPermissionsTeamId() : null;
        // Kein Cache-Zugriff hier: der Konstruktor und der Installer rufen das,
        // bevor es eine cache-Tabelle gibt. Der versionierte Schlüssel entsteht
        // beim ersten Lesen der Rechte.
        $this->versionMemo = null;
        $this->cacheKey = $this->baseCacheKey;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return Collection<int, Model>
     */
    public function getPermissions(array $params = [], bool $onlyOne = false): Collection {
        $this->cacheKey = $this->scopedCacheKey();

        return parent::getPermissions($params, $onlyOne);
    }

    public function setPermissionsTeamId(int|string|Model|null $id): void {
        parent::setPermissionsTeamId($id);
        if (! $this->teams) {
            return;
        }
        $teamId = $this->getPermissionsTeamId();
        if ($teamId !== $this->scopedTeamId) {
            // Anderes Team, anderer Schlüssel: die geladene Sammlung gehört dem alten.
            $this->scopedTeamId = $teamId;
            $this->permissions = null;
            $this->forgetWildcardPermissionIndex();
        }
        $this->cacheKey = $this->scopedCacheKey();
    }

    public function forgetCachedPermissions(): bool {
        $this->permissions = null;
        $this->forgetWildcardPermissionIndex();
        $next = $this->version() + 1;
        $this->cache->forever(self::versionKey(), $next);
        $this->cache->forget($this->cacheKey);
        $this->versionMemo = $next;
        $this->cacheKey = $this->scopedCacheKey();

        return true;
    }

    /**
     * Nur Rollen des aktuellen Teams und globale Rollen (`team_id` NULL); ohne Team wie Spatie.
     *
     * @return Collection<int, Model>
     */
    protected function getPermissionsWithRoles(): Collection {
        if (! $this->teams || $this->scopedTeamId === null) {
            return parent::getPermissionsWithRoles();
        }
        $teamId = $this->scopedTeamId;
        $column = $this->teamsKey;

        return $this->permissionClass::select()
            ->with(['roles' => static fn ($q) => $q->where(static fn ($q2) => $q2->whereNull($column)->orWhere($column, $teamId))])
            ->get();
    }

    public function scopedCacheKey(): string {
        $team = $this->teams ? ($this->scopedTeamId ?? 'all') : 'all';

        return $this->baseCacheKey . ':v' . $this->version() . ':t' . $team;
    }

    public function version(): int {
        return $this->versionMemo ??= (int) $this->cache->get(self::versionKey(), 1);
    }

    /** Versionsschlüssel im Rechte-Cache-Store; der Menü-Cache liest ihn mit (perf-13). */
    public static function versionKey(): string {
        return (string) config('permission.cache.key') . ':version';
    }
}
