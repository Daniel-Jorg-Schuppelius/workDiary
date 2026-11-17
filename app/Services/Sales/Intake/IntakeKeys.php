<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeKeys.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Intake;

use App\Services\Fields\{FieldSchema, FieldValues};

/**
 * Schlüssel im Bogen der Aufnahmeakte (Feature 200): Grundteil ohne Präfix
 * (`request`), Fristmerkmale eines Regelsatzes als `rs_<regelsatz>__<merkmal>`,
 * Felder eines Prüfpakets als `<paket>__<feld>`, Unterlagen als
 * `<paket>__doc_<unterlage>`. Ein Paketschlüssel enthält nie `__` und beginnt
 * nie mit `rs_`; so trennt der Doppelunterstrich eindeutig.
 */
final class IntakeKeys {
    public const string SEPARATOR = '__';

    public const string RULE_SET_PREFIX = 'rs_';

    public const string DOCUMENT_PREFIX = 'doc_';

    /** Abschnitt, den die Akte vor ein Paket ohne eigenen Abschnitt setzt (Paketschlüssel davor). */
    public const string PACKAGE_SECTION = 'section_package';

    /** Abschnitt der Unterlagenliste eines Pakets (Paketschlüssel davor). */
    public const string DOCUMENTS_SECTION = 'section_documents';

    /** Meta-Typ der Anhänge zu einer Unterlage: `intake_doc:<feldschlüssel>`. */
    public const string ATTACHMENT_META = 'intake_doc:';

    /** Paketschlüssel: kurz, ohne Doppelunterstrich, nicht `rs_…`. */
    public const string PACKAGE_PATTERN = '/^(?!rs_)[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/';

    public const int PACKAGE_KEY_MAX = 24;

    public static function isPackageKey(string $key): bool {
        return strlen($key) <= self::PACKAGE_KEY_MAX && preg_match(self::PACKAGE_PATTERN, $key) === 1;
    }

    public static function package(string $package, string $field): string {
        return $package . self::SEPARATOR . $field;
    }

    public static function document(string $package, string $document): string {
        return self::package($package, self::DOCUMENT_PREFIX . $document);
    }

    public static function ruleSet(string $ruleSet, string $trait): string {
        return self::RULE_SET_PREFIX . $ruleSet . self::SEPARATOR . $trait;
    }

    /** Paket eines Feldschlüssels; null für Grundteil und Fristmerkmale. */
    public static function packageOf(string $key): ?string {
        if (str_starts_with($key, self::RULE_SET_PREFIX) || ! str_contains($key, self::SEPARATOR)) {
            return null;
        }

        return explode(self::SEPARATOR, $key, 2)[0];
    }

    /**
     * Regelsatz und Merkmal eines Fristmerkmal-Schlüssels.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function ruleSetOf(string $key): ?array {
        if (! str_starts_with($key, self::RULE_SET_PREFIX) || ! str_contains($key, self::SEPARATOR)) {
            return null;
        }
        [$ruleSet, $trait] = explode(self::SEPARATOR, substr($key, strlen(self::RULE_SET_PREFIX)), 2);

        return [$ruleSet, $trait];
    }

    public static function isDocument(string $key): bool {
        $package = self::packageOf($key);

        return $package !== null && str_starts_with(substr($key, strlen($package) + strlen(self::SEPARATOR)), self::DOCUMENT_PREFIX);
    }

    /** Werte eines Pakets mit Schlüsseln ohne Präfix (für das Paket selbst). */
    public static function scoped(FieldValues $values, string $package): FieldValues {
        $prefix = $package . self::SEPARATOR;
        $out = [];
        foreach ($values->toArray() as $key => $value) {
            if (str_starts_with((string) $key, $prefix)) {
                $out[substr($key, strlen($prefix))] = $value;
            }
        }

        return new FieldValues($out);
    }

    /**
     * Schema eines Pakets mit Präfix; Bedingungen auf Felder desselben Pakets
     * werden mitgeführt.
     */
    public static function prefixed(FieldSchema $schema, string $package): FieldSchema {
        $keys = array_fill_keys($schema->keys(), true);
        $rows = [];
        foreach ($schema->toArray() as $row) {
            $row['key'] = self::package($package, $row['key']);
            if (is_array($row['visible_if'] ?? null) && isset($keys[$row['visible_if']['field']])) {
                $row['visible_if']['field'] = self::package($package, $row['visible_if']['field']);
            }
            $rows[] = $row;
        }

        return FieldSchema::fromArray($rows);
    }
}
