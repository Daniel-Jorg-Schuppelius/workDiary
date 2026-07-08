<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NormProfileRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use InvalidArgumentException;
use RuntimeException;

/**
 * Registry der Normprofile (Feature 046, Inkrement A).
 *
 * Lädt die Profil-Dateien aus config/isms-norms/ (eine PHP-Datei je
 * Normprofil) und validiert beim Laden das einheitliche Schema:
 *
 *   ['key' => 'iso27001-2022', 'norm' => 'ISO/IEC 27001',
 *    'edition' => '2022', 'label' => '…',
 *    'requirements' => [['ref_no' => '4.1', 'title' => '…'], …]]
 *
 * Inhaltlich liegen dort AUSSCHLIESSLICH Referenznummern mit eigenen
 * deutschen Kurztiteln (HLS-Hauptkapitel, beim ISO/IEC-27001-Profil
 * zusätzlich Annex A) — KEINE Normtexte (Urheberrecht). Die Profil-Labels
 * kommen bewusst aus der Config und nicht aus lang/ (Datenbestand, keine
 * UI-Übersetzung). Schema-Verstöße werfen eine RuntimeException mit
 * Dateiname und Grund; unbekannte Profil-Keys eine
 * InvalidArgumentException.
 */
class NormProfileRegistry {
    /**
     * @var array<string, array{key: string, norm: string, edition: string, label: string, version: string, as_of: ?string, requirements: list<array{ref_no: string, title: string}>}>|null
     */
    private ?array $profiles = null;

    /**
     * @param  string|null  $path  Profil-Verzeichnis (Default: config/isms-norms) — für Tests überschreibbar.
     */
    public function __construct(
        private readonly ?string $path = null,
    ) {}

    /**
     * Alle Profile: key → Metadaten (ohne Anforderungsliste).
     *
     * @return array<string, array{key: string, norm: string, edition: string, label: string, version: string, as_of: ?string, requirements_count: int}>
     */
    public function all(): array {
        $all = [];
        foreach ($this->load() as $key => $profile) {
            $all[$key] = $this->meta($profile);
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
     * Metadaten eines Profils.
     *
     * @return array{key: string, norm: string, edition: string, label: string, version: string, as_of: ?string, requirements_count: int}
     *
     * @throws InvalidArgumentException bei unbekanntem Profil-Key
     */
    public function get(string $key): array {
        $profiles = $this->load();

        if (! array_key_exists($key, $profiles)) {
            throw new InvalidArgumentException("Unbekanntes Normprofil: {$key}");
        }

        return $this->meta($profiles[$key]);
    }

    /**
     * Anforderungsliste (Ref-Nr. + Kurztitel) eines Profils.
     *
     * @return list<array{ref_no: string, title: string}>
     *
     * @throws InvalidArgumentException bei unbekanntem Profil-Key
     */
    public function requirements(string $key): array {
        $profiles = $this->load();

        if (! array_key_exists($key, $profiles)) {
            throw new InvalidArgumentException("Unbekanntes Normprofil: {$key}");
        }

        return $profiles[$key]['requirements'];
    }

    /**
     * Lädt und validiert alle Profil-Dateien (einmal pro Instanz).
     *
     * @return array<string, array{key: string, norm: string, edition: string, label: string, version: string, as_of: ?string, requirements: list<array{ref_no: string, title: string}>}>
     *
     * @throws RuntimeException bei Schema-Verstößen
     */
    private function load(): array {
        if ($this->profiles !== null) {
            return $this->profiles;
        }

        $directory = $this->path ?? config_path('isms-norms');
        $files = glob($directory . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);

        $profiles = [];
        foreach ($files as $file) {
            $profile = $this->validate($file, require $file);

            if (array_key_exists($profile['key'], $profiles)) {
                throw new RuntimeException("Normprofil-Datei {$file}: doppelter key '{$profile['key']}'.");
            }

            $profiles[$profile['key']] = $profile;
        }

        return $this->profiles = $profiles;
    }

    /**
     * Validiert das Profil-Schema einer Datei.
     *
     * @return array{key: string, norm: string, edition: string, label: string, version: string, as_of: ?string, requirements: list<array{ref_no: string, title: string}>}
     *
     * @throws RuntimeException bei Schema-Verstößen
     */
    private function validate(string $file, mixed $data): array {
        $fail = static function (string $reason) use ($file): never {
            throw new RuntimeException("Normprofil-Datei {$file}: {$reason}");
        };

        if (! is_array($data)) {
            $fail('muss ein Array zurückgeben.');
        }

        foreach (['key', 'norm', 'edition', 'label'] as $field) {
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

        if (! isset($data['requirements']) || ! is_array($data['requirements']) || ! array_is_list($data['requirements']) || $data['requirements'] === []) {
            $fail("'requirements' fehlt oder ist keine nicht-leere Liste.");
        }

        $requirements = [];
        $seen = [];
        foreach ($data['requirements'] as $index => $entry) {
            if (! is_array($entry)
                || ! isset($entry['ref_no'], $entry['title'])
                || ! is_string($entry['ref_no']) || trim($entry['ref_no']) === ''
                || ! is_string($entry['title']) || trim($entry['title']) === ''
            ) {
                $fail("requirements[{$index}] benötigt nicht-leere Strings 'ref_no' und 'title'.");
            }

            $refNo = (string) $entry['ref_no'];
            if (isset($seen[$refNo])) {
                $fail("requirements[{$index}]: doppelte ref_no '{$refNo}'.");
            }
            $seen[$refNo] = true;

            $requirements[] = ['ref_no' => $refNo, 'title' => (string) $entry['title']];
        }

        // Versionsmetadaten (Nachtrag 046a): Profilrevision + Stichtag der
        // zugrunde liegenden Normfassung — optional, Default '1.0'/null.
        $version = isset($data['version']) ? trim((string) $data['version']) : '1.0';
        $asOf = isset($data['as_of']) ? trim((string) $data['as_of']) : null;
        if ($asOf !== null && $asOf !== '' && strtotime($asOf) === false) {
            $fail("'as_of' ('{$asOf}') ist kein gültiges Datum.");
        }

        return [
            'key' => $key,
            'norm' => (string) $data['norm'],
            'edition' => (string) $data['edition'],
            'label' => (string) $data['label'],
            'version' => $version !== '' ? $version : '1.0',
            'as_of' => $asOf !== '' ? $asOf : null,
            'requirements' => $requirements,
        ];
    }

    /**
     * @param  array{key: string, norm: string, edition: string, label: string, version: string, as_of: ?string, requirements: list<array{ref_no: string, title: string}>}  $profile
     * @return array{key: string, norm: string, edition: string, label: string, version: string, as_of: ?string, requirements_count: int}
     */
    private function meta(array $profile): array {
        return [
            'key' => $profile['key'],
            'norm' => $profile['norm'],
            'edition' => $profile['edition'],
            'label' => $profile['label'],
            'version' => $profile['version'],
            'as_of' => $profile['as_of'],
            'requirements_count' => count($profile['requirements']),
        ];
    }

    /**
     * Profil-Versionsmetadaten zu einer Norm/Edition (Nachtrag 046a) —
     * null, wenn kein kuratiertes Profil zur Kombination existiert.
     *
     * @return array{version: string, as_of: ?string}|null
     */
    public function findByNorm(string $norm, string $edition): ?array {
        foreach ($this->all() as $profile) {
            if ($profile['norm'] === $norm && $profile['edition'] === $edition) {
                return ['version' => $profile['version'], 'as_of' => $profile['as_of']];
            }
        }

        return null;
    }
}
