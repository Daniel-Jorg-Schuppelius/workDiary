<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetentionRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Retention;

use App\Models\Platform\Organization;
use App\Settings\SettingsRegistry;
use App\Support\Trans;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Zentrale Aufbewahrungs-Registry (Restpunkte 66+67): Fristen und
 * Rechtsgrundlagen kommen aus config/retention.php, aufgelöst über den
 * Rechtsraum der Organisation (organizations.legal_region, Fallback DE).
 * Die je Datenbereich registrierten {@see RetentionPolicy}-Einträge liefern
 * die überfälligen Datensätze für den Review-Scan (privacy:retention-scan)
 * — Vorschläge statt Direktlöschung.
 */
class RetentionRegistry {
    /** @var array<string, RetentionPolicy> */
    private array $policies = [];

    public function register(RetentionPolicy $policy): void {
        $this->policies[$policy->area] = $policy;
    }

    /** @return array<string, RetentionPolicy> */
    public function policies(): array {
        return $this->policies;
    }

    public function policy(string $area): ?RetentionPolicy {
        return $this->policies[$area] ?? null;
    }

    /** Anzeigename des Bereichs in der aktiven Sprache; unbekannte Bereiche roh. */
    public function labelFor(string $area): string {
        return Trans::or("privacy.retention_area.{$area}", $area);
    }

    /** Rechtsraum der Org (Fallback: config default_region → DE). */
    public function regionFor(Organization $organization): string {
        $region = strtoupper(trim((string) ($organization->getAttribute('legal_region') ?? '')));
        if ($region === '') {
            $region = strtoupper((string) config('retention.default_region', 'DE'));
        }

        return $region;
    }

    /** @return list<string> Rechtsräume, für die mindestens ein Bereich eine Frist nennt. */
    public function regions(): array {
        $regions = [strtoupper((string) config('retention.default_region', 'DE'))];
        foreach ((array) config('retention.areas', []) as $area) {
            array_push($regions, ...array_map(strval(...), array_keys((array) ($area['years'] ?? []))));
        }
        $regions = array_values(array_unique($regions));
        sort($regions);

        return $regions;
    }

    /**
     * Aufbewahrungsdauer in Jahren für Bereich+Rechtsraum (Fallback DE);
     * null, wenn der Bereich nicht konfiguriert ist. Nennt der Bereich eine
     * Einstellung (`years_setting`), gilt deren Wert der Organisation vor dem
     * Rechtsraum (MVP-1683).
     */
    public function yearsFor(Organization $organization, string $area): ?int {
        $own = $this->organizationYearsFor($organization, $area);
        if ($own !== null) {
            return $own;
        }

        return $this->defaultYearsFor($organization, $area);
    }

    /** Frist des Rechtsraums ohne Einstellung der Organisation. */
    public function defaultYearsFor(Organization $organization, string $area): ?int {
        $years = (array) config("retention.areas.{$area}.years", []);
        $region = $this->regionFor($organization);

        $value = $years[$region] ?? $years['DE'] ?? null;

        return $value !== null ? (int) $value : null;
    }

    /** Registry-Schlüssel, über den die Organisation die Frist des Bereichs setzt; null = nicht einstellbar. */
    public function yearsSettingFor(string $area): ?string {
        $key = config("retention.areas.{$area}.years_setting");

        return is_string($key) && $key !== '' ? $key : null;
    }

    /** Eigene Frist der Organisation in Jahren; null ohne Einstellung. */
    public function organizationYearsFor(Organization $organization, string $area): ?int {
        $key = $this->yearsSettingFor($area);
        if ($key === null) {
            return null;
        }
        // Ohne Override liefert die Registry INF — toIntOrNull nimmt nur endliche Zahlen.
        $years = NumberHelper::toIntOrNull(SettingsRegistry::organizationValue($organization, $key));

        return $years !== null && $years > 0 ? $years : null;
    }

    /**
     * Tages-Frist eines Bereichs (Feature 130): entweder direkt (`days`) oder
     * per Verweis auf eine bestehende Config-Frist (`days_source`, z. B.
     * location.retention_days — kein Doppeln der Quelle). Rechtsraum-neutral;
     * null, wenn keine Tages-Frist konfiguriert oder <= 0 (unbegrenzt).
     */
    public function daysFor(string $area): ?int {
        $value = config("retention.areas.{$area}.days");
        if ($value === null) {
            $source = config("retention.areas.{$area}.days_source");
            $value = is_string($source) ? config($source) : null;
        }

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /** Rechtsgrundlagen-Label für Bereich+Rechtsraum (Fallback DE). */
    public function basisFor(Organization $organization, string $area): ?string {
        $basis = (array) config("retention.areas.{$area}.basis", []);
        $region = $this->regionFor($organization);

        $value = $basis[$region] ?? $basis['DE'] ?? null;

        return $value !== null ? (string) $value : null;
    }

    /** Stichtag, vor dem Datensätze des Bereichs löschbar werden. */
    public function cutoffFor(Organization $organization, string $area): ?CarbonImmutable {
        $years = $this->yearsFor($organization, $area);
        if ($years !== null) {
            return CarbonImmutable::now()->subYears($years);
        }

        $days = $this->daysFor($area);
        if ($days !== null) {
            return CarbonImmutable::now()->subDays($days);
        }

        return null;
    }
}
