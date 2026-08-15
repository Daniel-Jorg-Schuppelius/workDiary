<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogImportDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Procurement\CatalogSourceFormat;
use App\Models\{SupplierCatalogImport, SupplierCatalogSource};
use RuntimeException;

/**
 * Wählt den formatabhängigen Import-Service (CSV/XLSX/DATANORM/BMEcat) und schreibt
 * je Lauf ein Protokoll ({@see SupplierCatalogImport}) — egal ob manuell oder
 * geplant ausgelöst (Feature 050, MVP-091). Genutzt vom Controller und vom
 * Cron-Command.
 */
class CatalogImportDispatcher {
    /**
     * @param  array<string, string>  $mapping
     * @return array{rows: int, created: int, updated: int, unchanged: int, price_changed: int, discontinued: int}
     *
     * @throws RuntimeException Importfehler werden protokolliert und weitergereicht.
     */
    public function run(SupplierCatalogSource $source, string $content, array $mapping, string $trigger): array {
        try {
            $summary = match ($source->format) {
                CatalogSourceFormat::Datanorm => app(DatanormImportService::class)->import($source, $content),
                CatalogSourceFormat::BMEcat => app(BMEcatImportService::class)->import($source, $content),
                CatalogSourceFormat::Xlsx => app(CatalogXlsxImportService::class)->import($source, $content, $mapping),
                default => app(CatalogCsvImportService::class)->import($source, $content, $mapping),
            };
        } catch (RuntimeException $e) {
            $this->recordFailure($source, $trigger, $e->getMessage());

            throw $e;
        }

        $this->record($source, $trigger, SupplierCatalogImport::STATUS_SUCCESS, $summary, $source->last_file_hash, null);

        return $summary;
    }

    /** Protokolliert einen fehlgeschlagenen Lauf (z. B. Abruf-/Verbindungsfehler). */
    public function recordFailure(SupplierCatalogSource $source, string $trigger, string $message): void {
        $this->record($source, $trigger, SupplierCatalogImport::STATUS_ERROR, [], null, $message);
    }

    /**
     * @param  array{rows?: int, created?: int, updated?: int, unchanged?: int, price_changed?: int, discontinued?: int}  $summary
     */
    private function record(SupplierCatalogSource $source, string $trigger, string $status, array $summary, ?string $fileHash, ?string $error): void {
        SupplierCatalogImport::query()->create([
            'organization_id' => $source->organization_id,
            'supplier_catalog_source_id' => $source->id,
            'trigger' => $trigger,
            'status' => $status,
            'rows' => $summary['rows'] ?? 0,
            'created' => $summary['created'] ?? 0,
            'updated' => $summary['updated'] ?? 0,
            'unchanged' => $summary['unchanged'] ?? 0,
            'price_changed' => $summary['price_changed'] ?? 0,
            'discontinued' => $summary['discontinued'] ?? 0,
            'error' => $error !== null ? mb_substr($error, 0, 2000) : null,
            'file_hash' => $fileHash,
        ]);
    }
}
