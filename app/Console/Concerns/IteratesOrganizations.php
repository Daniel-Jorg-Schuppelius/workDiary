<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IteratesOrganizations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\{Builder, Collection};
use Throwable;

/**
 * Gemeinsames Org-Iterations-Skelett der Console-Commands (Konsolidierung C6):
 * Einzel-Org-Option → Query, Kontext-Binding mit Restore im finally (A6-Fix,
 * Muster ScanComplianceFindingsCommand) und Throwable-Fang je Organisation.
 */
trait IteratesOrganizations {
    /** Wortgleiche Options-Definition des Skeletts — für Command-Signaturen. */
    public const ORGANIZATION_OPTION = '{--organization= : ID einer einzelnen Organisation, sonst alle}';

    /**
     * Organisationen gemäß Einzel-Org-Option (leer/fehlend → alle).
     *
     * @param  string  $option  Name der Option (Default `organization`, manche Commands nutzen `org`)
     * @param  (callable(Builder<Organization>): mixed)|null  $scope  Query-Anpassung (z. B. withoutGlobalScopes)
     * @return Collection<int, Organization>
     */
    protected function organizationsToProcess(string $option = 'organization', ?callable $scope = null): Collection {
        $query = Organization::query();
        if ($scope !== null) {
            $scope($query);
        }

        $orgId = $this->option($option);
        if ($orgId !== null && $orgId !== '') {
            $query->whereKey((int) $orgId);
        }

        return $query->get();
    }

    /**
     * Führt $fn mit gebundenem `currentOrganization`-Kontext aus und stellt im
     * finally den vorherigen Zustand wieder her (A6-Fix: kein hängenbleibendes
     * Binding nach Command-Ende).
     *
     * @param  callable(Organization): mixed  $fn
     */
    protected function withOrganizationContext(Organization $organization, callable $fn): mixed {
        $bound = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        $previous = $bound instanceof Organization ? $bound : null;

        app()->instance('currentOrganization', $organization);

        try {
            return $fn($organization);
        } finally {
            if ($previous !== null) {
                app()->instance('currentOrganization', $previous);
            } else {
                app()->forgetInstance('currentOrganization');
            }
        }
    }

    /**
     * Komplettes Skelett: iteriert die Organisationen, bindet je Org den
     * Kontext (mit Restore), fängt Throwable je Org und läuft weiter.
     *
     * @param  callable(Organization): mixed  $fn
     * @param  (callable(Organization, Throwable): void)|null  $onError  Default: error-Zeile „Organisation #id (name): Abbruch — …"
     * @param  (callable(Builder<Organization>): mixed)|null  $scope
     * @return int Anzahl fehlgeschlagener Organisationen
     */
    protected function forEachOrganization(callable $fn, ?callable $onError = null, string $option = 'organization', ?callable $scope = null): int {
        $failures = 0;

        foreach ($this->organizationsToProcess($option, $scope) as $organization) {
            try {
                $this->withOrganizationContext($organization, $fn);
            } catch (Throwable $e) {
                $failures++;
                if ($onError !== null) {
                    $onError($organization, $e);
                } else {
                    $this->error(sprintf('Organisation #%d (%s): Abbruch — %s', $organization->id, $organization->name, $e->getMessage()));
                }
            }
        }

        return $failures;
    }
}
