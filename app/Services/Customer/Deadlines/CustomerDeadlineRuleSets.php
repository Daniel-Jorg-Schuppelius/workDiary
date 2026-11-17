<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineRuleSets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Deadlines;

use App\Models\Platform\Organization;
use App\Services\Customer\Deadlines\Contracts\CustomerDeadlineRuleSet;
use App\Services\Licensing\ModuleExtensions;
use App\Settings\{SettingScope, SettingsRegistry};

/**
 * Regelsätze der Kundenfristen einer Organisation (Feature 197, MVP-1630).
 * Verfügbar ist, was ein für die Organisation aktives Modul meldet
 * ({@see ModuleExtensions::activeFor()}); eingeschaltet ist davon, was in der
 * Einstellung `customer_deadlines.rule_sets` steht — gesetzt vom
 * Branchenprofil (Schlüssel `deadline_rule_sets`, ergänzt nur) oder im
 * Einstellungsdialog der Fristenübersicht.
 *
 * Vorwarnung: `customer_deadlines.warn_days` (Vorgabe 7 Tage), je Regelsatz
 * überschreibbar über `customer_deadlines.warn_days_by_rule_set`.
 */
final class CustomerDeadlineRuleSets {
    public const string SETTING_RULE_SETS = 'customer_deadlines.rule_sets';

    public const string SETTING_WARN_DAYS = 'customer_deadlines.warn_days';

    public const string SETTING_WARN_DAYS_BY_RULE_SET = 'customer_deadlines.warn_days_by_rule_set';

    public const int DEFAULT_WARN_DAYS = 7;

    public const int MAX_WARN_DAYS = 90;

    public function __construct(
        private readonly ModuleExtensions $extensions,
        private readonly SettingsRegistry $settings,
    ) {}

    /**
     * Von aktiven Modulen gemeldete Regelsätze, nach Schlüssel.
     *
     * @return array<string, CustomerDeadlineRuleSet>
     */
    public function available(Organization|int $organization): array {
        $out = [];
        foreach ($this->extensions->activeFor(CustomerDeadlineRuleSet::class, $organization) as $class) {
            $ruleSet = app($class);
            if ($ruleSet instanceof CustomerDeadlineRuleSet) {
                $out[$ruleSet->key()] = $ruleSet;
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * Eingeschaltete und verfügbare Regelsätze.
     *
     * @return array<string, CustomerDeadlineRuleSet>
     */
    public function enabled(Organization $organization): array {
        return array_intersect_key($this->available($organization), array_flip($this->enabledKeys($organization)));
    }

    /** Regelsatz zum Schlüssel, auch wenn ausgeschaltet (Anzeige vorhandener Fristen); null, wenn kein aktives Modul ihn meldet. */
    public function get(Organization|int $organization, string $key): ?CustomerDeadlineRuleSet {
        return $this->available($organization)[$key] ?? null;
    }

    /** @return list<string> */
    public function enabledKeys(Organization $organization): array {
        $value = SettingsRegistry::organizationValue($organization, self::SETTING_RULE_SETS);

        return is_array($value) ? array_values(array_unique(array_filter($value, static fn (mixed $key): bool => is_string($key) && $key !== ''))) : [];
    }

    /**
     * Schaltet genau die genannten (verfügbaren) Regelsätze ein.
     *
     * @param  list<string>  $keys
     */
    public function setEnabled(Organization $organization, array $keys): void {
        $keys = array_values(array_intersect(array_unique($keys), array_keys($this->available($organization))));
        sort($keys);
        $this->settings->set(self::SETTING_RULE_SETS, $keys, SettingScope::Organization, $organization);
    }

    /**
     * Ergänzt Regelsätze, ohne andere abzuschalten (Branchenprofil).
     *
     * @param  list<string>  $keys
     * @return int Anzahl neu eingeschalteter
     */
    public function enable(Organization $organization, array $keys): int {
        $current = $this->enabledKeys($organization);
        $known = array_keys($this->available($organization));
        $added = array_values(array_diff(array_intersect($keys, $known), $current));
        if ($added !== []) {
            $this->setEnabled($organization, [...$current, ...$added]);
        }

        return count($added);
    }

    /** Vorwarnung in Tagen für einen Regelsatz: Wert des Regelsatzes, sonst der Organisation, sonst 7. */
    public function warnDays(Organization $organization, string $ruleSet): int {
        $overrides = $this->warnDaysByRuleSet($organization);
        if (isset($overrides[$ruleSet])) {
            return $overrides[$ruleSet];
        }

        return $this->defaultWarnDays($organization);
    }

    /** Vorwarnung der Organisation (Vorgabe 7 Tage). */
    public function defaultWarnDays(Organization $organization): int {
        $value = SettingsRegistry::organizationValue($organization, self::SETTING_WARN_DAYS);

        return $value !== \INF && is_numeric($value) ? max(0, min(self::MAX_WARN_DAYS, (int) $value)) : self::DEFAULT_WARN_DAYS;
    }

    /** @return array<string, int> Regelsatz → Tage (nur überschriebene) */
    public function warnDaysByRuleSet(Organization $organization): array {
        $value = SettingsRegistry::organizationValue($organization, self::SETTING_WARN_DAYS_BY_RULE_SET);
        $out = [];
        foreach (is_array($value) ? $value : [] as $key => $days) {
            if (is_string($key) && is_numeric($days)) {
                $out[$key] = max(0, min(self::MAX_WARN_DAYS, (int) $days));
            }
        }

        return $out;
    }

    /**
     * Vorwarnung speichern: Vorgabe der Organisation und Abweichungen je Regelsatz
     * (leer = Vorgabe der Organisation).
     *
     * @param  array<string, int|null>  $byRuleSet
     */
    public function saveWarnDays(Organization $organization, int $days, array $byRuleSet): void {
        $this->settings->set(self::SETTING_WARN_DAYS, max(0, min(self::MAX_WARN_DAYS, $days)), SettingScope::Organization, $organization);
        $overrides = [];
        foreach ($byRuleSet as $key => $value) {
            if ($value !== null && isset($this->available($organization)[$key])) {
                $overrides[$key] = max(0, min(self::MAX_WARN_DAYS, $value));
            }
        }
        $this->settings->set(self::SETTING_WARN_DAYS_BY_RULE_SET, $overrides, SettingScope::Organization, $organization);
    }
}
