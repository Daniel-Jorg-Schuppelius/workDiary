<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrosswalkRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use InvalidArgumentException;
use RuntimeException;

/**
 * Registry der Norm-Crosswalks (Feature 044/046, Nachtrag NIST).
 *
 * Lädt die Crosswalk-Dateien aus config/isms-crosswalks/ (eine PHP-Datei
 * je Zuordnung) und validiert beim Laden das einheitliche Schema:
 *
 *   ['key' => 'nist-csf-2-0--iso27001-2022',
 *    'source_norm' => 'NIST CSF', 'source_edition' => '2.0',
 *    'target_norm' => 'ISO/IEC 27001', 'target_edition' => '2022',
 *    'label' => '…', 'version' => '1.0', 'as_of' => '2024-02-26',
 *    'mappings' => [['source_ref' => 'GV.OC', 'target_refs' => ['4.1', …]], …]]
 *
 * Inhaltlich sind das AUSSCHLIESSLICH Referenznummern (keine Normtexte);
 * die Zuordnung ist eine fachliche Orientierung, keine amtliche
 * Konformitätszusage. Schema-Verstöße werfen eine RuntimeException mit
 * Dateiname und Grund; unbekannte Keys eine InvalidArgumentException.
 */
class CrosswalkRegistry {
    /**
     * @var array<string, array{key: string, source_norm: string, source_edition: string, target_norm: string, target_edition: string, label: string, version: string, as_of: ?string, mappings: list<array{source_ref: string, target_refs: list<string>}>}>|null
     */
    private ?array $crosswalks = null;

    /**
     * @param  string|null  $path  Crosswalk-Verzeichnis (Default: config/isms-crosswalks) — für Tests überschreibbar.
     */
    public function __construct(
        private readonly ?string $path = null,
    ) {}

    /**
     * Alle Crosswalks: key → Metadaten (ohne Mapping-Liste).
     *
     * @return array<string, array{key: string, source_norm: string, source_edition: string, target_norm: string, target_edition: string, label: string, version: string, as_of: ?string, mappings_count: int}>
     */
    public function all(): array {
        $all = [];
        foreach ($this->load() as $key => $crosswalk) {
            $all[$key] = $this->meta($crosswalk);
        }

        return $all;
    }

    /** @return list<string> */
    public function keys(): array {
        return array_keys($this->load());
    }

    public function has(string $key): bool {
        return array_key_exists($key, $this->load());
    }

    /**
     * Metadaten eines Crosswalks.
     *
     * @return array{key: string, source_norm: string, source_edition: string, target_norm: string, target_edition: string, label: string, version: string, as_of: ?string, mappings_count: int}
     *
     * @throws InvalidArgumentException bei unbekanntem Key
     */
    public function get(string $key): array {
        $crosswalks = $this->load();

        if (! array_key_exists($key, $crosswalks)) {
            throw new InvalidArgumentException("Unbekannter Crosswalk: {$key}");
        }

        return $this->meta($crosswalks[$key]);
    }

    /**
     * Mapping-Liste (source_ref → target_refs) eines Crosswalks.
     *
     * @return list<array{source_ref: string, target_refs: list<string>}>
     *
     * @throws InvalidArgumentException bei unbekanntem Key
     */
    public function mappings(string $key): array {
        $crosswalks = $this->load();

        if (! array_key_exists($key, $crosswalks)) {
            throw new InvalidArgumentException("Unbekannter Crosswalk: {$key}");
        }

        return $crosswalks[$key]['mappings'];
    }

    /**
     * Findet den ersten Crosswalk-Key mit passender Quell-/Zielnorm — null,
     * wenn keiner existiert.
     */
    public function findKey(string $sourceNorm, string $sourceEdition, string $targetNorm, string $targetEdition): ?string {
        foreach ($this->load() as $key => $crosswalk) {
            if (
                $crosswalk['source_norm'] === $sourceNorm
                && $crosswalk['source_edition'] === $sourceEdition
                && $crosswalk['target_norm'] === $targetNorm
                && $crosswalk['target_edition'] === $targetEdition
            ) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Lädt und validiert alle Crosswalk-Dateien (einmal pro Instanz).
     *
     * @return array<string, array{key: string, source_norm: string, source_edition: string, target_norm: string, target_edition: string, label: string, version: string, as_of: ?string, mappings: list<array{source_ref: string, target_refs: list<string>}>}>
     *
     * @throws RuntimeException bei Schema-Verstößen
     */
    private function load(): array {
        if ($this->crosswalks !== null) {
            return $this->crosswalks;
        }

        $directory = $this->path ?? config_path('isms-crosswalks');
        $files = glob($directory . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);

        $crosswalks = [];
        foreach ($files as $file) {
            $crosswalk = $this->validate($file, require $file);

            if (array_key_exists($crosswalk['key'], $crosswalks)) {
                throw new RuntimeException("Crosswalk-Datei {$file}: doppelter key '{$crosswalk['key']}'.");
            }

            $crosswalks[$crosswalk['key']] = $crosswalk;
        }

        return $this->crosswalks = $crosswalks;
    }

    /**
     * Validiert das Crosswalk-Schema einer Datei.
     *
     * @return array{key: string, source_norm: string, source_edition: string, target_norm: string, target_edition: string, label: string, version: string, as_of: ?string, mappings: list<array{source_ref: string, target_refs: list<string>}>}
     *
     * @throws RuntimeException bei Schema-Verstößen
     */
    private function validate(string $file, mixed $data): array {
        $fail = static function (string $reason) use ($file): never {
            throw new RuntimeException("Crosswalk-Datei {$file}: {$reason}");
        };

        if (! is_array($data)) {
            $fail('muss ein Array zurückgeben.');
        }

        foreach (['key', 'source_norm', 'source_edition', 'target_norm', 'target_edition', 'label'] as $field) {
            if (! isset($data[$field]) || ! is_string($data[$field]) || trim($data[$field]) === '') {
                $fail("'{$field}' fehlt oder ist kein nicht-leerer String.");
            }
        }

        $key = (string) $data['key'];
        if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $key) !== 1) {
            $fail("'key' ('{$key}') muss aus Kleinbuchstaben, Ziffern und '-' bestehen.");
        }
        if ($key !== basename($file, '.php')) {
            $fail("'key' ('{$key}') muss dem Dateinamen entsprechen.");
        }

        if (! isset($data['mappings']) || ! is_array($data['mappings']) || ! array_is_list($data['mappings']) || $data['mappings'] === []) {
            $fail("'mappings' fehlt oder ist keine nicht-leere Liste.");
        }

        $mappings = [];
        $seen = [];
        foreach ($data['mappings'] as $index => $entry) {
            if (
                ! is_array($entry)
                || ! isset($entry['source_ref'], $entry['target_refs'])
                || ! is_string($entry['source_ref']) || trim($entry['source_ref']) === ''
                || ! is_array($entry['target_refs']) || ! array_is_list($entry['target_refs']) || $entry['target_refs'] === []
            ) {
                $fail("mappings[{$index}] benötigt einen nicht-leeren 'source_ref' und eine nicht-leere Liste 'target_refs'.");
            }

            $sourceRef = (string) $entry['source_ref'];
            if (isset($seen[$sourceRef])) {
                $fail("mappings[{$index}]: doppelte source_ref '{$sourceRef}'.");
            }
            $seen[$sourceRef] = true;

            $targetRefs = [];
            foreach ($entry['target_refs'] as $targetIndex => $targetRef) {
                if (! is_string($targetRef) || trim($targetRef) === '') {
                    $fail("mappings[{$index}].target_refs[{$targetIndex}] ist kein nicht-leerer String.");
                }
                $targetRefs[] = (string) $targetRef;
            }

            $mappings[] = ['source_ref' => $sourceRef, 'target_refs' => array_values(array_unique($targetRefs))];
        }

        $version = isset($data['version']) ? trim((string) $data['version']) : '1.0';
        $asOf = isset($data['as_of']) ? trim((string) $data['as_of']) : null;
        if ($asOf !== null && $asOf !== '' && strtotime($asOf) === false) {
            $fail("'as_of' ('{$asOf}') ist kein gültiges Datum.");
        }

        return [
            'key' => $key,
            'source_norm' => (string) $data['source_norm'],
            'source_edition' => (string) $data['source_edition'],
            'target_norm' => (string) $data['target_norm'],
            'target_edition' => (string) $data['target_edition'],
            'label' => (string) $data['label'],
            'version' => $version !== '' ? $version : '1.0',
            'as_of' => $asOf !== '' ? $asOf : null,
            'mappings' => $mappings,
        ];
    }

    /**
     * @param  array{key: string, source_norm: string, source_edition: string, target_norm: string, target_edition: string, label: string, version: string, as_of: ?string, mappings: list<array{source_ref: string, target_refs: list<string>}>}  $crosswalk
     * @return array{key: string, source_norm: string, source_edition: string, target_norm: string, target_edition: string, label: string, version: string, as_of: ?string, mappings_count: int}
     */
    private function meta(array $crosswalk): array {
        return [
            'key' => $crosswalk['key'],
            'source_norm' => $crosswalk['source_norm'],
            'source_edition' => $crosswalk['source_edition'],
            'target_norm' => $crosswalk['target_norm'],
            'target_edition' => $crosswalk['target_edition'],
            'label' => $crosswalk['label'],
            'version' => $crosswalk['version'],
            'as_of' => $crosswalk['as_of'],
            'mappings_count' => count($crosswalk['mappings']),
        ];
    }
}
