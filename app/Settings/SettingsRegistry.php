<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SettingsRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Settings;

use App\Models\{Organization, SystemSetting};
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Single Source of Truth für registrierte Einstellungen (Feature 067,
 * MVP-173). Definitionen kommen als reine Arrays aus
 * config/settings-registry.php (config-cachebar, keine Closures) und
 * werden hier zu DTOs hydriert.
 *
 * Die Registry speichert keine Werte: Org-Werte liegen in
 * organizations.settings, System-Werte in system_settings, Defaults in
 * config/*.php. effective() erklärt die Herkunft.
 */
class SettingsRegistry {
    /** @var array<string, SettingDefinition>|null */
    private ?array $definitions = null;

    /** @return array<string, SettingDefinition> */
    public function all(): array {
        if ($this->definitions === null) {
            $this->definitions = [];
            /** @var array<string, array<string, mixed>> $raw */
            $raw = (array) config('settings-registry', []);
            foreach ($raw as $key => $data) {
                $this->definitions[$key] = SettingDefinition::fromArray($key, $data);
            }
        }

        return $this->definitions;
    }

    public function has(string $key): bool {
        return array_key_exists($key, $this->all());
    }

    public function definition(string $key): SettingDefinition {
        $definition = $this->all()[$key] ?? null;
        if ($definition === null) {
            throw new InvalidArgumentException("Setting [{$key}] ist nicht registriert.");
        }

        return $definition;
    }

    /** @return array<string, SettingDefinition> */
    public function forScope(SettingScope $scope): array {
        return array_filter($this->all(), fn(SettingDefinition $d): bool => $d->allowsScope($scope));
    }

    /**
     * Formular-Validierungsregeln für alle Keys eines Scopes
     * (MVP-174/P3b) — Single Source of Truth für den settings.*-Block
     * des Organisationsformulars: je Key `prefix + key => formRules()`
     * plus `sometimes|array`-Regeln für jede vorkommende Gruppe.
     *
     * @return array<string, list<string>>
     */
    public function formRulesForScope(SettingScope $scope, string $prefix = 'settings.'): array {
        $rules = [];
        $groups = [];
        foreach ($this->forScope($scope) as $key => $definition) {
            $groups[$definition->group()] = true;
            $rules[$prefix . $key] = $definition->formRules();

            // Zwischen-Arrays verschachtelter Keys (a.b.c → a.b) erlauben.
            $segments = explode('.', $key);
            for ($i = 2; $i < count($segments); $i++) {
                $rules[$prefix . implode('.', array_slice($segments, 0, $i))] ??= ['sometimes', 'array'];
            }
        }
        foreach (array_keys($groups) as $group) {
            $rules[$prefix . $group] ??= ['sometimes', 'array'];
        }

        return $rules;
    }

    /**
     * Effektiver Wert + Herkunft: Org-Override → System-Override →
     * config() → Registry-Fallback.
     */
    public function effective(string $key, ?Organization $organization = null): EffectiveValue {
        $definition = $this->definition($key);

        if ($organization !== null && $definition->allowsScope(SettingScope::Organization)) {
            $value = self::organizationValue($organization, $key);
            if ($value !== \INF) {
                return new EffectiveValue($value, SettingSource::Organization, $definition);
            }
        }

        if ($definition->allowsScope(SettingScope::System)) {
            $systemValues = SystemSetting::valueMap();
            if (array_key_exists($key, $systemValues)) {
                return new EffectiveValue($systemValues[$key], SettingSource::System, $definition);
            }
        }

        $configValue = config($key, \INF);
        if ($configValue !== \INF) {
            return new EffectiveValue($configValue, SettingSource::Config, $definition);
        }

        return new EffectiveValue($definition->fallback, SettingSource::Default, $definition);
    }

    /**
     * Einziger validierter Schreibweg (MVP-173). System-Werte landen in
     * system_settings (sensitiv → verschlüsselt), Org-Werte im
     * auditierten organizations.settings-JSON.
     */
    public function set(string $key, mixed $value, SettingScope $scope, ?Organization $organization = null, ?int $userId = null): void {
        $definition = $this->definition($key);
        $this->assertWritableScope($definition, $scope, $organization);
        $this->validate($definition, $value);

        if ($scope === SettingScope::System) {
            $setting = SystemSetting::query()->firstOrNew(['key' => $key]);
            $setting->setResolvedValue($value, $definition->sensitive);
            $setting->updated_by_user_id = $userId;
            $setting->save();

            return;
        }

        assert($organization !== null);
        $settings = (array) ($organization->settings ?? []);
        data_set($settings, $key, $value);
        $organization->settings = $settings;
        $organization->save();
    }

    /** Rollback auf Default: entfernt den Override der jeweiligen Ebene. */
    public function reset(string $key, SettingScope $scope, ?Organization $organization = null): void {
        $definition = $this->definition($key);
        $this->assertWritableScope($definition, $scope, $organization);

        if ($scope === SettingScope::System) {
            SystemSetting::query()->where('key', $key)->get()->each->delete();

            return;
        }

        assert($organization !== null);
        $settings = (array) ($organization->settings ?? []);
        [$group, $rest] = array_pad(explode('.', $key, 2), 2, null);
        if ($rest === null || !isset($settings[$group])) {
            return;
        }
        $groupSettings = (array) $settings[$group];
        data_forget($groupSettings, $rest);
        if ($groupSettings === []) {
            unset($settings[$group]);
        } else {
            $settings[$group] = $groupSettings;
        }
        $organization->settings = $settings;
        $organization->save();
    }

    /** Org-Override-Rohwert (INF = nicht gesetzt). */
    public static function organizationValue(Organization $organization, string $key): mixed {
        [$group, $rest] = array_pad(explode('.', $key, 2), 2, null);
        if ($group === null || $rest === null || $rest === '') {
            return \INF;
        }
        /** @var array<string, mixed> $settings */
        $settings = (array) ($organization->settings ?? []);
        /** @var array<string, mixed> $stored */
        $stored = (array) ($settings[$group] ?? []);

        return data_get($stored, $rest, \INF);
    }

    private function assertWritableScope(SettingDefinition $definition, SettingScope $scope, ?Organization $organization): void {
        if ($scope === SettingScope::User) {
            throw new InvalidArgumentException('User-Präferenzen werden über User::setPreference gepflegt, nicht über die Registry.');
        }
        if (!$definition->allowsScope($scope)) {
            throw new InvalidArgumentException("Setting [{$definition->key}] erlaubt den Scope [{$scope->value}] nicht.");
        }
        if ($scope === SettingScope::Organization && $organization === null) {
            throw new InvalidArgumentException('Organisations-Override braucht eine Organisation.');
        }
    }

    private function validate(SettingDefinition $definition, mixed $value): void {
        $validator = Validator::make(
            ['value' => $value],
            ['value' => $definition->validationRules()],
        );
        $validator->validate();
    }
}
