<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurgeFilePointerRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Services\Org\{OrganizationFileTables, OrganizationLifecycleService};
use ReflectionClass;
use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate zum Sicherheitsaudit 2026-10-04 (li-6): die endgültige Löschung einer
 * Organisation sammelt Dateien über eine feste Liste ein. Tabellen, die nach
 * ihr entstanden, fehlten — ihre Dateien blieben ohne Zeile auf der Platte.
 *
 * Jede Tabelle mit Dateizeiger (`*_path`, `path`, `storage_key`) steht in
 * `OrganizationLifecycleService::FILE_POINTER_TABLES` bzw. meldet sich als
 * Plugin über {@see OrganizationFileTables} an — oder steht hier mit Grund.
 */
class PurgeFilePointerRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> "tabelle.spalte" => Grund */
    private const NOT_A_LOCAL_FILE = [
        'caldav_connections.calendar_path' => 'Pfad auf dem fremden CalDAV-Server.',
        'cloud_document_connections.root_folder_path' => 'Ordner beim Cloud-Anbieter.',
        'cloud_document_items.source_path' => 'Pfad beim Cloud-Anbieter.',
        'supplier_catalog_sources.remote_path' => 'Pfad auf dem Server des Lieferanten.',
        'meter_readings.photo_path' => 'Wert aus der Eingabe; die App legt dort keine Datei ab — darf nie ein Löschziel sein.',
        'learning_certificates.pdf_path' => 'Wird nicht beschrieben; das PDF entsteht beim Abruf.',
    ];

    /** @var array<string, string> Tabellen ohne `organization_id` => wie ihre Dateien erfasst werden */
    private const WITHOUT_ORGANIZATION_COLUMN = [
        'document_versions' => 'Über `documents.organization_id` eingesammelt (`fileTargetsFor()`).',
        'protocol_signatures' => '`signature_image_path` wird nicht beschrieben; der Wert kam früher aus der Eingabe und darf nie ein Löschziel sein.',
    ];

    public function test_every_file_pointer_column_is_covered_by_the_purge(): void {
        /** @var array<string, array{path: string|list<string>}> $core */
        $core = (new ReflectionClass(OrganizationLifecycleService::class))->getConstant('FILE_POINTER_TABLES');
        $listed = [...$core, ...app(OrganizationFileTables::class)->all()];

        $violations = [];
        $seen = [];
        $pointers = 0;
        foreach ($this->schemaTables() as $table => $definition) {
            $columns = array_keys($definition['columns']);
            $pointerColumns = array_values(array_filter($columns, static fn (string $column): bool => preg_match('/(^|_)path$|(^|_)storage_key$/', $column) === 1));
            if ($pointerColumns === []) {
                continue;
            }
            if (! in_array('organization_id', $columns, true)) {
                if (isset(self::WITHOUT_ORGANIZATION_COLUMN[$table])) {
                    $seen[$table] = true;
                } else {
                    $violations[] = "{$table}: Dateizeiger ohne organization_id — Erfassung in WITHOUT_ORGANIZATION_COLUMN begründen";
                }

                continue;
            }
            foreach ($pointerColumns as $column) {
                $pointers++;
                $key = $table . '.' . $column;
                if (isset(self::NOT_A_LOCAL_FILE[$key])) {
                    $seen[$key] = true;

                    continue;
                }
                if (! in_array($column, (array) ($listed[$table]['path'] ?? []), true)) {
                    $violations[] = $key;
                }
            }
        }
        sort($violations);

        $this->assertGreaterThan(20, $pointers, 'Die Suche findet die Dateizeiger nicht mehr.');
        $this->assertSame([], $violations, "Dateizeiger fehlt in der Löschung der Organisation (FILE_POINTER_TABLES bzw. OrganizationFileTables) — nur aufnehmen, wenn die App den Pfad selbst vergibt:\n" . implode("\n", $violations));
        $this->assertSame([], array_values(array_diff([...array_keys(self::NOT_A_LOCAL_FILE), ...array_keys(self::WITHOUT_ORGANIZATION_COLUMN)], array_keys($seen))), 'Veraltete Ausnahmen.');
    }

    public function test_listed_pointer_columns_exist(): void {
        /** @var array<string, array{path: string|list<string>}> $core */
        $core = (new ReflectionClass(OrganizationLifecycleService::class))->getConstant('FILE_POINTER_TABLES');
        $schema = $this->schemaTables();

        $missing = [];
        foreach ($core as $table => $spec) {
            foreach ((array) $spec['path'] as $column) {
                if (! isset($schema[$table]['columns'][$column])) {
                    $missing[] = $table . '.' . $column;
                }
            }
        }

        $this->assertSame([], $missing, 'FILE_POINTER_TABLES nennt Spalten, die es nicht gibt: ' . implode(', ', $missing));
    }
}
