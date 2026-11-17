<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProfileIntakeCheckPackage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Intake\Packages;

use App\Enums\Sales\IntakeSensitivity;
use App\Models\Platform\Organization;
use App\Services\Fields\{FieldSchema, FieldValues};
use App\Services\Sales\Intake\Contracts\IntakeCheckPackage;
use App\Services\Sales\Intake\Dto\IntakeRetentionRule;
use App\Services\Sales\Intake\IntakeKeys;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Prüfpaket als Profildaten (Feature 200, MVP-1641/1675): Felder,
 * Unterlagen, Aufbewahrung, Aktualisierung, enge Sichtbarkeit und Übernahme
 * an den Kunden stehen im Branchenprofil (Schlüssel `intake_packages`, Katalog
 * `database/data/intake_packages.php`) bzw. in der Organisationseinstellung
 * `lead_intake.package_definitions`. {@see normalize()} prüft die Profildaten
 * streng; ungültige Pakete werden nicht übernommen.
 *
 * @phpstan-type Definition array{key: string, label: string, requires_decision: bool, fields: list<array<string, mixed>>, documents: array<string, string>, retention: array{years: int, basis: string}|null, refresh_months: int|null, sensitive: array<string, string>, transfer: array{deadline_traits?: array<string, array<string, string>>, classifications?: array<string, string>, customer?: array<string, string>}}
 */
final class ProfileIntakeCheckPackage implements IntakeCheckPackage {
    /** Kundenspalten, die ein Paket als Vorschlag setzen darf (nur wenn am Kunden leer). */
    public const array CUSTOMER_COLUMNS = ['vat_id', 'payment_terms_days'];

    public const int REFRESH_MAX_MONTHS = 120;

    private const string DOCUMENT_KEY_PATTERN = '/^[a-z][a-z0-9_]{0,39}$/';

    /** @param  Definition  $definition */
    public function __construct(private readonly array $definition) {}

    /**
     * Profilzeile → geprüfte Definition, null bei ungültigen Daten.
     *
     * @param  array<array-key, mixed>  $row
     * @return Definition|null
     */
    public static function normalize(array $row): ?array {
        $key = (string) ($row['key'] ?? $row['code'] ?? '');
        $label = StringHelper::trimToNull(is_scalar($row['label'] ?? null) ? (string) $row['label'] : null);
        if (! IntakeKeys::isPackageKey($key) || $label === null || mb_strlen($label) > 120 || ! is_array($row['fields'] ?? null)) {
            return null;
        }
        try {
            $schema = FieldSchema::fromRows($row['fields']);
        } catch (ValidationException) {
            return null;
        }
        foreach ($schema as $field) {
            // Dateien laufen über die Unterlagenliste, `doc_` ist ihr Präfix; die Abschnitte der Akte sind reserviert.
            if ($field->type->storesAttachment() || str_starts_with($field->key, IntakeKeys::DOCUMENT_PREFIX)
                || in_array($field->key, [IntakeKeys::PACKAGE_SECTION, IntakeKeys::DOCUMENTS_SECTION], true)) {
                return null;
            }
        }
        $schema = self::withOptionLabels($schema, array_values($row['fields']));

        // Profil: Liste aus Bezeichnungen oder {key, label}; gespeicherte Form: Schlüssel → Bezeichnung.
        $documents = [];
        foreach ((array) ($row['documents'] ?? []) as $index => $document) {
            $docLabel = is_array($document) ? (string) ($document['label'] ?? '') : (is_scalar($document) ? (string) $document : '');
            $docLabel = trim($docLabel);
            $docKey = match (true) {
                is_array($document) && is_string($document['key'] ?? null) => $document['key'],
                is_string($index) => $index,
                default => Str::slug(Str::limit($docLabel, 40, ''), '_'),
            };
            if ($docLabel === '' || preg_match(self::DOCUMENT_KEY_PATTERN, $docKey) !== 1 || isset($documents[$docKey])) {
                return null;
            }
            $documents[$docKey] = Str::limit($docLabel, 160, '');
        }

        $fieldKeys = array_fill_keys($schema->keys(), true);
        foreach (array_keys($documents) as $docKey) {
            $fieldKeys[IntakeKeys::DOCUMENT_PREFIX . $docKey] = true;
        }

        // Profil: Art → Feldschlüssel; gespeicherte Form: Feldschlüssel → Art.
        $sensitive = [];
        foreach ((array) ($row['sensitive'] ?? []) as $category => $keys) {
            $pairs = is_string($keys) ? [[(string) $category, $keys]] : array_map(static fn (mixed $key): array => [$key, $category], (array) $keys);
            foreach ($pairs as [$fieldKey, $kindValue]) {
                $kind = is_string($kindValue) ? IntakeSensitivity::tryFrom($kindValue) : null;
                if ($kind === null || ! is_string($fieldKey) || ! isset($fieldKeys[$fieldKey])) {
                    return null;
                }
                $sensitive[$fieldKey] = $kind->value;
            }
        }

        $transfer = self::transferFrom((array) ($row['transfer'] ?? []), $fieldKeys);
        if ($transfer === null) {
            return null;
        }
        $refresh = $row['refresh_months'] ?? null;
        $retention = IntakeRetentionRule::fromArray($row['retention'] ?? null);

        return [
            'key' => $key,
            'label' => $label,
            'requires_decision' => StringHelper::parseBool($row['requires_decision'] ?? false) ?? false,
            'fields' => $schema->toArray(),
            'documents' => $documents,
            'retention' => $retention?->toArray(),
            'refresh_months' => is_numeric($refresh) && (int) $refresh > 0 ? min(self::REFRESH_MAX_MONTHS, (int) $refresh) : null,
            'sensitive' => $sensitive,
            'transfer' => $transfer,
        ];
    }

    public function key(): string {
        return $this->definition['key'];
    }

    public function label(): string {
        return $this->definition['label'];
    }

    public function schema(): FieldSchema {
        return FieldSchema::fromArray($this->definition['fields']);
    }

    public function documents(): array {
        return $this->definition['documents'];
    }

    public function requiredBeforeDecision(): bool {
        return $this->definition['requires_decision'];
    }

    public function retention(): ?IntakeRetentionRule {
        return IntakeRetentionRule::fromArray($this->definition['retention']);
    }

    public function refreshInterval(FieldValues $values, Organization $organization): ?int {
        return $this->definition['refresh_months'];
    }

    public function sensitiveFields(): array {
        $out = [];
        foreach ($this->definition['sensitive'] as $field => $category) {
            $kind = IntakeSensitivity::tryFrom($category);
            if ($kind !== null) {
                $out[$field] = $kind;
            }
        }

        return $out;
    }

    public function transfer(): array {
        return $this->definition['transfer'];
    }

    /**
     * Anzeigetexte der Auswahlwerte (`option_labels`, etwa `pg3` → „Pflegegrad 3“)
     * übernimmt der Dialog-Leser nicht; hier nur für vorhandene Werte.
     *
     * @param  list<mixed>  $rows  Profilzeilen in Schema-Reihenfolge
     */
    private static function withOptionLabels(FieldSchema $schema, array $rows): FieldSchema {
        $out = [];
        foreach ($schema->toArray() as $i => $definition) {
            $labels = is_array($rows[$i] ?? null) && is_array($rows[$i]['option_labels'] ?? null) ? $rows[$i]['option_labels'] : [];
            $clean = [];
            foreach ($labels as $option => $text) {
                if (in_array((string) $option, $definition['options'], true) && is_scalar($text) && trim((string) $text) !== '') {
                    $clean[(string) $option] = Str::limit(trim((string) $text), 120, '');
                }
            }
            if ($clean !== []) {
                $definition['option_labels'] = $clean;
            }
            $out[] = $definition;
        }

        return FieldSchema::fromArray($out);
    }

    /**
     * @param  array<array-key, mixed>  $raw
     * @param  array<string, true>  $fieldKeys
     * @return array{deadline_traits?: array<string, array<string, string>>, classifications?: array<string, string>, customer?: array<string, string>}|null
     */
    private static function transferFrom(array $raw, array $fieldKeys): ?array {
        $out = [];
        foreach ((array) ($raw['deadline_traits'] ?? []) as $ruleSet => $traits) {
            if (! is_string($ruleSet) || ! is_array($traits)) {
                return null;
            }
            foreach ($traits as $trait => $field) {
                if (! is_string($trait) || ! is_string($field) || ! isset($fieldKeys[$field])) {
                    return null;
                }
                $out['deadline_traits'][$ruleSet][$trait] = $field;
            }
        }
        foreach ((array) ($raw['classifications'] ?? []) as $domain => $field) {
            if (! is_string($domain) || ! is_string($field) || ! isset($fieldKeys[$field])) {
                return null;
            }
            $out['classifications'][$domain] = $field;
        }
        foreach ((array) ($raw['customer'] ?? []) as $column => $field) {
            if (! in_array($column, self::CUSTOMER_COLUMNS, true) || ! is_string($field) || ! isset($fieldKeys[$field])) {
                return null;
            }
            $out['customer'][$column] = $field;
        }

        return $out;
    }
}
