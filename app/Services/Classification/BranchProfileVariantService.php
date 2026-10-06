<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BranchProfileVariantService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Classification;

use App\Enums\Classification\ClassificationDomain;
use App\Models\Classification\BranchProfileVariant;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\ModuleCatalog;
use App\Support\BranchProfileFiles;
use CommonToolkit\Helper\FileSystem\Folder;

/**
 * Profilvarianten (MVP-933): Die Variante überlagert ein mitgeliefertes
 * Branchenprofil (Bausteine entfernen, Ausschnitt ergänzen) und wird unter dem
 * Code des Basisprofils installiert — Version, Sprachbeilage und
 * Deinstallation bleiben so beim Basisprofil. Welche Variante installiert ist,
 * steht in `settings.branch_profile_variants`.
 */
final class BranchProfileVariantService {
    public const SETTINGS_KEY = 'branch_profile_variants';

    /** Abschnitte, die kein Baustein-Katalog sind. */
    private const HEAD = ['code', 'label', 'label_i18n', 'description', 'version', 'min_app_version'];

    public function __construct(private readonly BranchProfileInstaller $installer) {}

    /** @return array<string, string> Code → Bezeichnung der mitgelieferten Profile */
    public function bases(): array {
        $out = [];
        foreach (Folder::findByPattern(database_path('data/branchprofiles'), '*.php') as $file) {
            $code = pathinfo($file, PATHINFO_FILENAME);
            $profile = BranchProfileFiles::profile($code);
            if ($profile !== null) {
                $out[$code] = (string) ($profile['label'] ?? $code);
            }
        }
        asort($out);

        return $out;
    }

    /** Schlüssel eines Bausteins: Code, Name oder (Pflichtregel) Eintragsart/Domäne; Textzeilen stehen für sich. */
    public static function key(mixed $row): ?string {
        if (is_string($row)) {
            return $row;
        }
        if (! is_array($row)) {
            return null;
        }
        $key = (string) ($row['code'] ?? $row['name'] ?? '');
        if ($key === '' && isset($row['entry_type_code'])) {
            $key = $row['entry_type_code'] . '/' . ($row['required_domain'] instanceof \BackedEnum ? $row['required_domain']->value : (string) ($row['required_domain'] ?? ''));
        }

        return $key !== '' ? $key : null;
    }

    /**
     * Wählbare Bausteine des Basisprofils je Abschnitt (Klassifikationen als `domain/code`).
     *
     * @param array<string, mixed> $profile
     * @return array<string, list<array{key: string, label: string}>>
     */
    public function options(array $profile): array {
        $out = [];
        foreach ($profile as $section => $value) {
            if (in_array($section, self::HEAD, true) || ! is_array($value)) {
                continue;
            }
            if ($section === 'classifications') {
                foreach ($value as $domain => $rows) {
                    foreach ((array) $rows as $row) {
                        $code = self::key($row);
                        if ($code !== null) {
                            $out[$section][] = ['key' => $domain . '/' . $code, 'label' => $domain . ': ' . (string) ($row['label'] ?? $code)];
                        }
                    }
                }

                continue;
            }
            foreach ($value as $row) {
                $key = self::key($row);
                if ($key !== null) {
                    $out[(string) $section][] = ['key' => $key, 'label' => is_array($row) ? (string) ($row['label'] ?? $row['name'] ?? $key) : $key];
                }
            }
        }

        return $out;
    }

    /**
     * Fehlertext, wenn ein Profil(-ausschnitt) unbekannte Domänen oder Module nennt; sonst null.
     *
     * @param array<array-key, mixed> $profile
     */
    public function profileError(array $profile): ?string {
        foreach (array_keys((array) ($profile['classifications'] ?? [])) as $domain) {
            if (ClassificationDomain::tryFrom((string) $domain) === null) {
                return (string) __('Unbekannte Klassifikations-Domäne ":domain" — Profil abgelehnt.', ['domain' => $domain]);
            }
        }
        foreach ((array) ($profile['modules_recommended'] ?? []) as $module) {
            if (! app(ModuleCatalog::class)->has((string) $module)) {
                return (string) __('Unbekanntes Modul ":module" in der Modul-Empfehlung — Profil abgelehnt.', ['module' => (string) $module]);
            }
        }

        return null;
    }

    /** @return array<string, mixed> Zusammengesetztes Profil unter dem Code des Basisprofils */
    public function compose(BranchProfileVariant $variant): array {
        $profile = BranchProfileFiles::profile($variant->base_code) ?? throw new \InvalidArgumentException('Unknown base profile ' . $variant->base_code);
        $removed = [];
        foreach ((array) $variant->removals as $section => $keys) {
            $removed[(string) $section] = array_flip(array_map('strval', (array) $keys));
        }

        foreach ($profile as $section => $value) {
            if (in_array($section, self::HEAD, true) || ! is_array($value) || ! isset($removed[$section])) {
                continue;
            }
            if ($section === 'classifications') {
                foreach ($value as $domain => $rows) {
                    $profile[$section][$domain] = array_values(array_filter((array) $rows, static fn (mixed $row): bool => ! isset($removed[$section][$domain . '/' . self::key($row)])));
                }

                continue;
            }
            $profile[$section] = array_values(array_filter($value, static fn (mixed $row): bool => ! isset($removed[$section][(string) self::key($row)])));
        }

        foreach ((array) $variant->additions as $section => $value) {
            if (in_array($section, self::HEAD, true) || ! is_array($value)) {
                continue;
            }
            if ($section === 'classifications') {
                foreach ($value as $domain => $rows) {
                    $profile[$section][$domain] = $this->mergeRows((array) ($profile[$section][$domain] ?? []), (array) $rows);
                }

                continue;
            }
            $profile[$section] = array_is_list($value) ? $this->mergeRows((array) ($profile[$section] ?? []), $value) : array_replace((array) ($profile[$section] ?? []), $value);
        }

        $profile['code'] = $variant->base_code;
        $profile['label'] = $variant->label;
        $profile['description'] = (string) ($variant->description ?? ($profile['description'] ?? ''));
        $profile['variant'] = $variant->code;

        return $profile;
    }

    /**
     * @return array{profile_code: string, version: int, created: array<string, int>, updated: array<string, int>, skipped: array<string, int>}
     */
    public function install(Organization $organization, BranchProfileVariant $variant, ?User $actor, bool $force): array {
        $result = $this->installer->installProfile($organization, $this->compose($variant), $actor, $force, $variant->base_code);
        $organization->refresh();
        $settings = (array) ($organization->settings ?? []);
        $settings[self::SETTINGS_KEY][$variant->base_code] = ['code' => $variant->code, 'version' => $variant->version];
        $organization->forceFill(['settings' => $settings])->save();

        return $result;
    }

    /**
     * Ergänzte Zeilen ersetzen gleichnamige Bausteine, sonst werden sie angehängt.
     *
     * @param array<array-key, mixed> $rows
     * @param array<array-key, mixed> $additions
     * @return list<mixed>
     */
    private function mergeRows(array $rows, array $additions): array {
        $byKey = [];
        foreach ($rows as $i => $row) {
            $key = self::key($row);
            if ($key !== null) {
                $byKey[$key] = $i;
            }
        }
        foreach ($additions as $row) {
            $key = self::key($row);
            if ($key !== null && isset($byKey[$key])) {
                $rows[$byKey[$key]] = $row;
            } else {
                $rows[] = $row;
            }
        }

        return array_values($rows);
    }
}
