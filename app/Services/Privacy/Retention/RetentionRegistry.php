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

namespace App\Services\Privacy\Retention;

use App\Models\Organization;
use Carbon\CarbonImmutable;

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

    /** Rechtsraum der Org (Fallback: config default_region → DE). */
    public function regionFor(Organization $organization): string {
        $region = strtoupper(trim((string) ($organization->getAttribute('legal_region') ?? '')));
        if ($region === '') {
            $region = strtoupper((string) config('retention.default_region', 'DE'));
        }

        return $region;
    }

    /**
     * Aufbewahrungsdauer in Jahren für Bereich+Rechtsraum (Fallback DE);
     * null, wenn der Bereich nicht konfiguriert ist.
     */
    public function yearsFor(Organization $organization, string $area): ?int {
        $years = (array) config("retention.areas.{$area}.years", []);
        $region = $this->regionFor($organization);

        $value = $years[$region] ?? $years['DE'] ?? null;

        return $value !== null ? (int) $value : null;
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
