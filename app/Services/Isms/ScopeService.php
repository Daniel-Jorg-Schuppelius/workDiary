<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScopeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use App\Models\Isms\IsmsScope;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Domain-Service Managementsystem-Geltungsbereiche (Feature 046).
 *
 * Geschäftsregeln:
 * - Pro Organisation existiert genau EIN Default-Scope
 *   („Gesamtorganisation", is_default = true) — ensureDefaultScope() legt
 *   ihn bei Bedarf an (genutzt von RequirementService-Import, RiskService
 *   und SoA).
 * - Der Default-Scope ist nicht löschbar; is_default ist nicht über
 *   create/update setzbar.
 */
class ScopeService {
    /**
     * Liefert den Default-Scope der Organisation — legt ihn bei Bedarf an.
     */
    public function ensureDefaultScope(int $organizationId): IsmsScope {
        $scope = IsmsScope::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('is_default', true)
            ->whereNull('deleted_at')
            ->first();

        if ($scope !== null) {
            return $scope;
        }

        return IsmsScope::query()->create([
            'organization_id' => $organizationId,
            'name' => __('isms.scope.default_name'),
            'description' => null,
            'is_default' => true,
        ]);
    }

    /**
     * Legt einen weiteren Geltungsbereich an (nie als Default).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $creator, array $attributes): IsmsScope {
        return DB::transaction(fn(): IsmsScope => IsmsScope::query()->create([
            'organization_id' => $creator->organization_id,
            'name' => $attributes['name'],
            'description' => $attributes['description'] ?? null,
            'is_default' => false,
        ]));
    }

    /**
     * Aktualisiert Name/Beschreibung (is_default bleibt unveränderlich).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(IsmsScope $scope, User $actor, array $attributes): IsmsScope {
        unset($actor);

        return DB::transaction(function () use ($scope, $attributes): IsmsScope {
            $scope->update([
                'name' => $attributes['name'] ?? $scope->name,
                'description' => array_key_exists('description', $attributes) ? $attributes['description'] : $scope->description,
            ]);

            return $scope;
        });
    }

    /**
     * Soft-Delete eines Geltungsbereichs — der Default-Scope ist geschützt.
     *
     * @throws ValidationException beim Versuch, den Default-Scope zu löschen
     */
    public function delete(IsmsScope $scope, User $actor): void {
        if ($scope->is_default) {
            throw ValidationException::withMessages([
                'scope' => __('isms.error.default_scope_undeletable'),
            ]);
        }

        DB::transaction(function () use ($scope, $actor): void {
            $scope->audit('isms.scope.deleted', ['actor_user_id' => $actor->id]);
            $scope->delete();
        });
    }
}
