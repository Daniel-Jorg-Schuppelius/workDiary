<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SettingDefinition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Settings;

use InvalidArgumentException;

/**
 * Metadaten einer registrierten Einstellung (Feature 067, MVP-173).
 *
 * Die Registry ist eine reine Definitions-Ebene: Sie speichert keine
 * Werte. Der Default ist eine Referenz auf config($key) — Werte werden
 * NICHT dupliziert; nur wenn kein config-Eintrag existiert, greift
 * $fallback.
 */
final readonly class SettingDefinition {
    /**
     * @param list<SettingScope> $scopes
     * @param list<string> $rules zusätzliche Laravel-Validierungsregeln
     * @param list<string|int|float>|null $options erlaubte Werte (Enum-Typ)
     * @param array{0: class-string, 1: string}|null $optionsFrom statische
     *        Methodenreferenz für DYNAMISCHE Optionslisten (z. B.
     *        [HolidayRegions::class, 'providers']) — als reine Referenz
     *        config-cachebar, aufgelöst erst zur Validierungszeit
     * @param list<string> $affects Job-Keys/Module für Risiko-Hinweise
     */
    public function __construct(
        public string $key,
        public SettingType $type,
        public array $scopes,
        public array $rules = [],
        public ?array $options = null,
        public ?array $optionsFrom = null,
        public bool $sensitive = false,
        public mixed $fallback = null,
        public array $affects = [],
    ) {
        if ($scopes === []) {
            throw new InvalidArgumentException("Setting [{$key}] braucht mindestens einen Scope.");
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(string $key, array $data): self {
        $scopes = array_map(
            static fn(string $scope): SettingScope => SettingScope::from($scope),
            (array) ($data['scopes'] ?? []),
        );

        $rules = $data['rules'] ?? [];
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        $optionsFrom = null;
        if (isset($data['options_from'])) {
            $ref = (array) $data['options_from'];
            if (count($ref) === 2 && is_string($ref[0]) && is_string($ref[1])) {
                /** @var array{0: class-string, 1: string} $ref */
                $optionsFrom = [$ref[0], $ref[1]];
            }
        }

        return new self(
            key: $key,
            type: SettingType::from((string) ($data['type'] ?? 'string')),
            scopes: array_values($scopes),
            rules: array_values($rules),
            options: isset($data['options']) ? array_values((array) $data['options']) : null,
            optionsFrom: $optionsFrom,
            sensitive: (bool) ($data['sensitive'] ?? false),
            fallback: $data['fallback'] ?? null,
            affects: array_values((array) ($data['affects'] ?? [])),
        );
    }

    public function allowsScope(SettingScope $scope): bool {
        return in_array($scope, $this->scopes, true);
    }

    /** Gruppe = Key-Segment vor dem ersten Punkt (config-Datei). */
    public function group(): string {
        return explode('.', $this->key, 2)[0];
    }

    /**
     * Erlaubte Werte — statisch (`options`) oder zur Laufzeit aufgelöst
     * (`options_from`, z. B. Provider-/Format-Listen).
     *
     * @return list<string|int|float>|null
     */
    public function resolvedOptions(): ?array {
        if ($this->options !== null) {
            return $this->options;
        }
        if ($this->optionsFrom !== null && is_callable($this->optionsFrom)) {
            /** @var list<string|int|float> $resolved */
            $resolved = array_values((array) call_user_func($this->optionsFrom));

            return $resolved;
        }

        return null;
    }

    /**
     * Vollständige Validierungsregeln inkl. Typ-Grundregel — für
     * KANONISCHE Werte (Setting::set, Registry-Adminseite).
     *
     * @return list<string>
     */
    public function validationRules(): array {
        $rules = [$this->type->baseRule(), ...$this->rules];
        $options = $this->resolvedOptions();
        if ($options !== null) {
            $rules[] = 'in:' . implode(',', array_map('strval', $options));
        }

        return $rules;
    }

    /**
     * Validierungsregeln für ROHEN Formular-Input (MVP-174/P3b):
     * HTML-Formulare liefern Strings — Booleans kommen als "0"/"1",
     * leere Strings bedeuten „Override entfernen" (stripEmpty im
     * Schreibpfad). Deshalb `nullable` + Roh-Input-Typregel statt der
     * kanonischen Wert-Regel.
     *
     * @return list<string>
     */
    public function formRules(): array {
        $typeRule = match ($this->type) {
            SettingType::Boolean => 'in:0,1',
            SettingType::Integer, SettingType::Duration => 'integer',
            SettingType::Decimal => 'numeric',
            SettingType::Time => 'date_format:H:i',
            SettingType::Json => 'array',
            SettingType::String_, SettingType::Enum_ => 'string',
        };

        // 'nullable' aus den Wert-Regeln nicht doppeln.
        $extra = array_values(array_filter($this->rules, static fn(string $r): bool => $r !== 'nullable'));

        $rules = ['nullable', $typeRule, ...$extra];
        $options = $this->resolvedOptions();
        if ($options !== null) {
            $rules[] = 'in:' . implode(',', array_map('strval', $options));
        }

        return $rules;
    }
}
