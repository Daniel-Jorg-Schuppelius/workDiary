<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakePackages.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Intake;

use App\Enums\Sales\IntakeRiskLevel;
use App\Models\Platform\Organization;
use App\Services\Licensing\ModuleExtensions;
use App\Services\Sales\Intake\Contracts\IntakeCheckPackage;
use App\Services\Sales\Intake\Packages\ProfileIntakeCheckPackage;
use App\Settings\{SettingScope, SettingsRegistry};

/**
 * Prüfpakete der Aufnahmeakte einer Organisation (Feature 200, MVP-1641).
 *
 * Verfügbar ist, was ein aktives Modul als Klasse meldet
 * ({@see ModuleExtensions::activeFor()}, GwG) und was als Profildaten in
 * `lead_intake.package_definitions` steht (Pflege-Erstaufnahme,
 * B2B-Neukunde). Eingeschaltet ist davon, was in `lead_intake.packages`
 * steht — gesetzt vom Branchenprofil (`intake_packages`, ergänzt nur) oder im
 * Einstellungsdialog der Aufnahmen. Ohne eingeschaltetes Paket mit
 * Entscheidungspflicht bleibt die Umwandlung der Leads wie bisher.
 *
 * Weitere Einstellungen: Aktualisierung je GwG-Risikostufe in Monaten
 * (`lead_intake.gwg_refresh_months`, Vorgabe jährlich) und zweiter Blick vor
 * der Annahme (`lead_intake.second_review`, Vorgabe aus).
 */
final class IntakePackages {
    public const string SETTING_ENABLED = 'lead_intake.packages';

    public const string SETTING_DEFINITIONS = 'lead_intake.package_definitions';

    public const string SETTING_GWG_REFRESH = 'lead_intake.gwg_refresh_months';

    public const string SETTING_SECOND_REVIEW = 'lead_intake.second_review';

    /** Vorgabe der Aktualisierung je Risikostufe: jährlich (Feature 200, MVP-1643). */
    public const int DEFAULT_REFRESH_MONTHS = 12;

    public const int MAX_REFRESH_MONTHS = 120;

    public function __construct(
        private readonly ModuleExtensions $extensions,
        private readonly SettingsRegistry $settings,
    ) {}

    /**
     * Verfügbare Pakete nach Schlüssel: Klassen aktiver Module, dann Profildaten.
     *
     * @return array<string, IntakeCheckPackage>
     */
    public function available(Organization $organization): array {
        $out = [];
        foreach ($this->extensions->activeFor(IntakeCheckPackage::class, $organization) as $class) {
            $package = app($class);
            if ($package instanceof IntakeCheckPackage) {
                $out[$package->key()] = $package;
            }
        }
        foreach ($this->definitions($organization) as $key => $definition) {
            $out[$key] ??= new ProfileIntakeCheckPackage($definition);
        }
        ksort($out);

        return $out;
    }

    /**
     * Eingeschaltete und verfügbare Pakete.
     *
     * @return array<string, IntakeCheckPackage>
     */
    public function enabled(Organization $organization): array {
        return array_intersect_key($this->available($organization), array_flip($this->enabledKeys($organization)));
    }

    public function get(Organization $organization, string $key): ?IntakeCheckPackage {
        return $this->available($organization)[$key] ?? null;
    }

    /** Sperrt ein eingeschaltetes Paket die Umwandlung ohne Annahme? */
    public function requiresDecision(Organization $organization): bool {
        foreach ($this->enabled($organization) as $package) {
            if ($package->requiredBeforeDecision()) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function enabledKeys(Organization $organization): array {
        $value = SettingsRegistry::organizationValue($organization, self::SETTING_ENABLED);

        return is_array($value) ? array_values(array_unique(array_filter($value, static fn (mixed $key): bool => is_string($key) && $key !== ''))) : [];
    }

    /**
     * Schaltet genau die genannten verfügbaren Pakete ein.
     *
     * @param  list<string>  $keys
     */
    public function setEnabled(Organization $organization, array $keys): void {
        $keys = array_values(array_intersect(array_unique($keys), array_keys($this->available($organization))));
        sort($keys);
        $this->write($organization, self::SETTING_ENABLED, $keys);
    }

    /**
     * Ergänzt Pakete, ohne andere abzuschalten (Branchenprofil).
     *
     * @param  list<string>  $keys
     * @return int Anzahl neu eingeschalteter
     */
    public function enable(Organization $organization, array $keys): int {
        $current = $this->enabledKeys($organization);
        $added = array_values(array_diff(array_intersect($keys, array_keys($this->available($organization))), $current));
        if ($added !== []) {
            $this->setEnabled($organization, [...$current, ...$added]);
        }

        return count($added);
    }

    /**
     * Paketdaten aus dem Profil, nach Schlüssel (nur gültige).
     *
     * @return array<string, array{key: string, label: string, requires_decision: bool, fields: list<array<string, mixed>>, documents: array<string, string>, retention: array{years: int, basis: string}|null, refresh_months: int|null, sensitive: array<string, string>, transfer: array{deadline_traits?: array<string, array<string, string>>, classifications?: array<string, string>, customer?: array<string, string>}}>
     */
    public function definitions(Organization $organization): array {
        $value = SettingsRegistry::organizationValue($organization, self::SETTING_DEFINITIONS);
        $out = [];
        foreach (is_array($value) ? $value : [] as $row) {
            $definition = is_array($row) ? ProfileIntakeCheckPackage::normalize($row) : null;
            if ($definition !== null) {
                $out[$definition['key']] = $definition;
            }
        }

        return $out;
    }

    /**
     * Paketdaten ergänzen: nur Schlüssel, die es noch nicht gibt — eine von der
     * Organisation geänderte oder ein gleichnamiges Klassenpaket bleibt.
     *
     * @param  list<array<array-key, mixed>>  $rows
     * @return list<string> Schlüssel der neu übernommenen Pakete
     */
    public function addDefinitions(Organization $organization, array $rows): array {
        $current = $this->definitions($organization);
        $classKeys = array_diff_key($this->available($organization), $current);
        $added = [];
        foreach ($rows as $row) {
            $definition = ProfileIntakeCheckPackage::normalize($row);
            if ($definition === null || isset($current[$definition['key']]) || isset($classKeys[$definition['key']])) {
                continue;
            }
            $current[$definition['key']] = $definition;
            $added[] = $definition['key'];
        }
        if ($added !== []) {
            $this->write($organization, self::SETTING_DEFINITIONS, array_values($current));
        }

        return $added;
    }

    /**
     * Aktualisierung je GwG-Risikostufe in Monaten (Vorgabe jährlich).
     *
     * @return array<string, int> Risikostufe → Monate
     */
    public function riskRefreshMonths(Organization $organization): array {
        $value = SettingsRegistry::organizationValue($organization, self::SETTING_GWG_REFRESH);
        $out = [];
        foreach (IntakeRiskLevel::cases() as $level) {
            $months = is_array($value) ? ($value[$level->value] ?? null) : null;
            $out[$level->value] = is_numeric($months) ? max(1, min(self::MAX_REFRESH_MONTHS, (int) $months)) : self::DEFAULT_REFRESH_MONTHS;
        }

        return $out;
    }

    /** @param  array<string, int>  $months  Risikostufe → Monate */
    public function saveRiskRefreshMonths(Organization $organization, array $months): void {
        $out = [];
        foreach (IntakeRiskLevel::cases() as $level) {
            $out[$level->value] = max(1, min(self::MAX_REFRESH_MONTHS, (int) ($months[$level->value] ?? self::DEFAULT_REFRESH_MONTHS)));
        }
        $this->write($organization, self::SETTING_GWG_REFRESH, $out);
    }

    /** Verlangt die Organisation einen zweiten Blick vor der Annahme (Vorgabe aus)? */
    public function secondReview(Organization $organization): bool {
        $value = SettingsRegistry::organizationValue($organization, self::SETTING_SECOND_REVIEW);

        return $value === true || $value === 1 || $value === '1';
    }

    public function setSecondReview(Organization $organization, bool $required): void {
        $this->write($organization, self::SETTING_SECOND_REVIEW, $required);
    }

    /** Über die Registry schreiben und die Organisation nachladen — Folgeaufrufe lesen ihre Einstellungen. */
    private function write(Organization $organization, string $key, mixed $value): void {
        $this->settings->set($key, $value, SettingScope::Organization, $organization);
        $organization->refresh();
    }
}
