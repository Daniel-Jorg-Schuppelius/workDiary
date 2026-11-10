<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IteratesModuleOrganizations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Concerns;

use App\Enums\Modules\ModuleKind;
use App\Models\Platform\Organization;
use App\Modules\ModuleRegistry;
use App\Services\Licensing\ModuleStatusResolver;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\{Builder, Collection, Model};

/**
 * Organisationsschleife geplanter Befehle eines Fachmoduls (MVP-1524, Konzept
 * „Modul-Konfiguration“ §5): Fachwirkung, Fremdabrufe und Benachrichtigungen
 * gibt es nur für Organisationen, in denen das Modul aktiv ist. Das Modul folgt
 * wie bei der Queue aus dem Domänenordner bzw. `#[BelongsToModule]`
 * ({@see ModuleRegistry::forClass()}). Gate `ModuleCommandRuleTest`.
 *
 * Kein Merken über Läufe hinweg: Artisan hält die Befehlsinstanz, ein
 * Modulwechsel zwischen zwei Läufen muss beim zweiten wirken.
 *
 * @mixin Command
 */
trait IteratesModuleOrganizations {
    use IteratesOrganizations {
        organizationsToProcess as private allOrganizationsToProcess;
    }

    /**
     * Organisationen der Einzel-Org-Option mit aktivem Modul; das Skelett
     * {@see IteratesOrganizations::forEachOrganization()} läuft darüber.
     *
     * @param  (callable(Builder<Organization>): mixed)|null  $scope
     * @return Collection<int, Organization>
     */
    protected function organizationsToProcess(string $option = 'organization', ?callable $scope = null): Collection {
        $organizations = $this->allOrganizationsToProcess($option, $scope);
        $explicit = ! in_array($this->option($option), [null, ''], true);

        return $organizations->filter(function (Organization $organization) use ($explicit): bool {
            if ($this->moduleActiveFor($organization)) {
                return true;
            }
            $message = sprintf('Organisation #%d (%s): Modul nicht aktiv — übersprungen.', $organization->id, $organization->name);
            $explicit ? $this->warn($message) : $this->line($message, null, 'v');

            return false;
        })->values();
    }

    protected function moduleActiveFor(Organization|int $organization): bool {
        $license = $this->moduleLicenseCode();
        if ($license === null) {
            return true;
        }
        $organization = $organization instanceof Organization ? $organization : Organization::query()->find($organization);

        return $organization instanceof Organization && app(ModuleStatusResolver::class)->isActiveFor($organization, $license);
    }

    /** @return list<int> Organisationen mit aktivem Modul, für Läufe über Datensätze */
    protected function moduleOrganizationIds(): array {
        return array_values(Organization::query()->orderBy('id')->get()
            ->filter(fn (Organization $organization): bool => $this->moduleActiveFor($organization))
            ->map(static fn (Organization $organization): int => (int) $organization->id)
            ->all());
    }

    /**
     * Läufe über Datensätze: nur Zeilen von Organisationen mit aktivem Modul.
     * Zeilen ohne Organisation (plattformweite Verbindungen) nur mit
     * `$withoutOrganization` und nur, solange eine Organisation das Modul nutzt.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function whereModuleActive(Builder $query, string $column = 'organization_id', bool $withoutOrganization = false): Builder {
        if ($this->moduleLicenseCode() === null) {
            return $query;
        }
        $ids = $this->moduleOrganizationIds();
        $query->where(static function (Builder $nested) use ($column, $ids, $withoutOrganization): void {
            $nested->whereIn($column, $ids);
            if ($withoutOrganization && $ids !== []) {
                $nested->orWhereNull($column);
            }
        });

        return $query;
    }

    /** Läufe ohne Organisationsbezug (Referenzdaten einer Fremdquelle): nur, solange eine Organisation das Modul nutzt. */
    protected function moduleActiveAnywhere(): bool {
        foreach (Organization::query()->orderBy('id')->cursor() as $organization) {
            if ($this->moduleActiveFor($organization)) {
                return true;
            }
        }

        return false;
    }

    private function moduleLicenseCode(): ?string {
        $manifest = app(ModuleRegistry::class)->forClass(static::class);

        return $manifest !== null && $manifest->kind() === ModuleKind::Feature ? $manifest->licenseCode() : null;
    }
}
