<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationDefaultRateResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Organization;
use App\Support\Setting;

/**
 * Organisationsweiter Standard-Stundensatz (MVP-482): letzte Stufe der
 * Satzhierarchie im {@see \App\Services\RateCalculator}, wenn weder Eintrag,
 * Kondition, Mitarbeiter, Tätigkeit, Projekt noch Kunde einen Satz setzen.
 *
 * Aufgelöst wird bewusst über die Organisation des Eintrags
 * (`groupSettings('invoicing')`), nicht über den ambienten
 * `currentOrganization`-Kontext: Nachbewertungsläufe und Scheduler-Importe
 * bewerten sonst mit dem Satz einer fremden bzw. gar keiner Organisation.
 *
 * Als `scoped` gebunden (AppServiceProvider): der Cache lebt pro Request bzw.
 * Queue-Job — ein langlebiger Worker rechnet so nie mit einem veralteten Satz.
 */
class OrganizationDefaultRateResolver {
    /** @var array<int, float|null> Request-/Job-Cache je organization_id. */
    private array $cache = [];

    public function flush(): void {
        $this->cache = [];
    }

    public function hourlyRateFor(?int $organizationId): ?float {
        if ($organizationId === null) {
            // Eintrag ohne Mandantenbezug: nur der ambiente Kontext bleibt.
            return self::toRate(Setting::get('invoicing.default_hourly_rate'));
        }

        if (! array_key_exists($organizationId, $this->cache)) {
            $organization = Organization::query()->withoutGlobalScopes()->find($organizationId);
            $this->cache[$organizationId] = $organization instanceof Organization
                ? self::toRate($organization->invoicingSettings()['default_hourly_rate'] ?? null)
                : null;
        }

        return $this->cache[$organizationId];
    }

    /** Leerer String/0 zählt als „kein Standardsatz" — sonst würde 0,00 € als gepflegter Satz gelten. */
    private static function toRate(mixed $value): ?float {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $rate = (float) $value;

        return $rate > 0.0 ? $rate : null;
    }
}
