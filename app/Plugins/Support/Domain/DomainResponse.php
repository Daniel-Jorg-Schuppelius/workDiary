<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainResponse.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Support\Domain;

use CommonToolkit\Helper\Data\CryptoHelper;

/**
 * Geparste DomainReselling-Antwort (Feature 083). Das Protokoll ist
 * UTF-8-Plaintext im INI-ähnlichen Format mit `PROPERTY[NAME][INDEX]=VALUE`.
 *
 * `hasEof` markiert die VOLLSTÄNDIGKEIT: fehlt der abschließende `EOF`-Marker,
 * gilt das Ergebnis als unklar und darf KEINE erfolgreiche Mutation
 * bestätigen. Property-Namen sind case-insensitive (hier klein normalisiert).
 *
 * Der Rohtext (`raw`) dient nur der Revisions-Hash-Bildung; er wird nie
 * geloggt (kann Auth-Codes/Kontaktdaten tragen).
 */
final class DomainResponse {
    /**
     * @param  array<string, array<int, string>>  $properties  name → index → value
     */
    public function __construct(
        public readonly int $code,
        public readonly string $description,
        public readonly array $properties,
        public readonly bool $hasEof,
        public readonly ?float $runtime = null,
        public readonly ?float $queuetime = null,
        public readonly string $raw = '',
    ) {}

    /** Vollständige Antwort (mit `EOF`) — Voraussetzung für jede Bestätigung. */
    public function isComplete(): bool {
        return $this->hasEof;
    }

    /** Erfolg NUR bei vollständiger Antwort und 2xx-Code. */
    public function isSuccess(): bool {
        return $this->hasEof && $this->code >= 200 && $this->code < 300;
    }

    /**
     * Alle Werte einer Property, nach Index sortiert.
     *
     * @return list<string>
     */
    public function property(string $name): array {
        $values = $this->properties[strtolower($name)] ?? [];
        ksort($values);

        return array_values($values);
    }

    /** Erster Wert einer Property oder null. */
    public function first(string $name): ?string {
        return $this->property($name)[0] ?? null;
    }

    /** Anzahl der Werte einer Property (z. B. Domainzahl in QueryDomainList). */
    public function count(string $name): int {
        return count($this->properties[strtolower($name)] ?? []);
    }

    /**
     * Korreliert Properties spaltenweise über den gemeinsamen Index: je Index
     * eine Zeile `name => value` (z. B. eine Domain je Zeile mit ihren Feldern).
     *
     * @return list<array<string, string>>
     */
    public function rows(): array {
        $indexes = [];
        foreach ($this->properties as $values) {
            foreach (array_keys($values) as $index) {
                $indexes[$index] = true;
            }
        }
        ksort($indexes);

        $rows = [];
        foreach (array_keys($indexes) as $index) {
            $row = [];
            foreach ($this->properties as $name => $values) {
                if (array_key_exists($index, $values)) {
                    $row[$name] = $values[$index];
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** Deterministischer Revisions-Hash über den Rohtext. */
    public function rawHash(): string {
        return CryptoHelper::hash($this->raw);
    }
}
