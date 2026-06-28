<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GaebImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Enums\Gaeb\{BoqItemStatus, BoqItemType, GaebImportStatus, GaebPhase};
use App\Models\{BillOfQuantity, BoqItem, BoqItemPriceSnapshot, BoqSection, GaebImport};
use Illuminate\Support\Facades\DB;

/**
 * GAEB-Importstrecke (Feature 049, MVP-081/082): parsen → Preflight → bei
 * sauberem Befund LV-Kopf, Abschnitte, Positionen und Preis-Snapshots schreiben.
 *
 * Ein Lauf mit blockierenden Preflight-Fehlern erzeugt nur ein Protokoll
 * ({@see GaebImport} im Status PreflightFailed) und schreibt keine Positionen.
 * Ein Reimport in ein bestehendes LV darf Positionen mit Ausführungsbezug nicht
 * still überschreiben — solche Fälle brechen mit {@see BoqImportConflictException}
 * ab (Status Conflict im Protokoll).
 */
class GaebImportService {
    public function __construct(
        private readonly GaebDaXmlParser $parser,
        private readonly GaebPreflight $preflight,
    ) {}

    /**
     * @param array{
     *     project_id?: int|null,
     *     diary_entry_id?: int|null,
     *     created_by?: int|null,
     *     bill_of_quantity_id?: int|null,
     *     name?: string|null
     * } $options
     */
    public function import(string $xml, string $filename, int $organizationId, array $options = []): GaebImport {
        $fileHash = hash('sha256', $xml);

        try {
            $parsed = $this->parser->parse($xml);
        } catch (GaebParseException $e) {
            return GaebImport::query()->create([
                'organization_id' => $organizationId,
                'filename' => $filename,
                'file_hash' => $fileHash,
                'status' => GaebImportStatus::PreflightFailed,
                'preflight' => ['ok' => false, 'errors' => [$e->getMessage()], 'warnings' => [], 'meta' => []],
                'created_by' => $options['created_by'] ?? null,
            ]);
        }

        $report = $this->preflight->check($parsed);

        if (!$report['ok']) {
            return GaebImport::query()->create([
                'organization_id' => $organizationId,
                'filename' => $filename,
                'file_hash' => $fileHash,
                'gaeb_version' => $parsed->version,
                'phase' => GaebPhase::fromCode($parsed->phase),
                'status' => GaebImportStatus::PreflightFailed,
                'section_count' => $parsed->sectionCount(),
                'item_count' => $parsed->itemCount(),
                'preflight' => $report,
                'created_by' => $options['created_by'] ?? null,
            ]);
        }

        $targetId = $options['bill_of_quantity_id'] ?? null;
        $target = $targetId !== null
            ? BillOfQuantity::query()->findOrFail($targetId)
            : null;

        if ($target !== null) {
            $this->guardReimport($target, $parsed);
        }

        return DB::transaction(function () use ($parsed, $filename, $organizationId, $options, $fileHash, $report, $target): GaebImport {
            $boq = $target ?? BillOfQuantity::query()->create([
                'organization_id' => $organizationId,
                'project_id' => $options['project_id'] ?? null,
                'diary_entry_id' => $options['diary_entry_id'] ?? null,
                'name' => $options['name'] ?? ($parsed->projectName ?? $filename),
                'external_id' => $parsed->externalId,
                'gaeb_version' => $parsed->version,
                'phase' => GaebPhase::fromCode($parsed->phase),
                'status' => BoqItemStatus::Imported,
                'created_by' => $options['created_by'] ?? null,
            ]);

            if ($target !== null) {
                // Reimport ohne Ausführungsbezug: alte Struktur ersetzen.
                $boq->items()->delete();
                $boq->sections()->delete();
            }

            $import = GaebImport::query()->create([
                'organization_id' => $organizationId,
                'bill_of_quantity_id' => $boq->id,
                'filename' => $filename,
                'file_hash' => $fileHash,
                'gaeb_version' => $parsed->version,
                'phase' => GaebPhase::fromCode($parsed->phase),
                'status' => GaebImportStatus::Imported,
                'section_count' => $parsed->sectionCount(),
                'item_count' => $parsed->itemCount(),
                'preflight' => $report,
                'created_by' => $options['created_by'] ?? null,
            ]);

            $sectionMap = $this->persistSections($boq, $organizationId, $parsed);
            $this->persistItems($boq, $import, $organizationId, $parsed, $sectionMap);

            return $import;
        });
    }

    /**
     * @return array<string, int> Map Ordnungszahl-Knoten → boq_sections.id
     */
    private function persistSections(BillOfQuantity $boq, int $organizationId, ParsedBoq $parsed): array {
        $map = [];
        foreach ($parsed->sections as $section) {
            $created = BoqSection::query()->create([
                'organization_id' => $organizationId,
                'bill_of_quantity_id' => $boq->id,
                'parent_id' => $section['parent_ref'] !== null ? ($map[$section['parent_ref']] ?? null) : null,
                'reference_no' => $section['ref'],
                'label' => $section['label'],
                'position' => $section['position'],
            ]);
            $map[$section['ref']] = $created->id;
        }

        return $map;
    }

    /**
     * @param array<string, int> $sectionMap
     */
    private function persistItems(BillOfQuantity $boq, GaebImport $import, int $organizationId, ParsedBoq $parsed, array $sectionMap): void {
        $phase = GaebPhase::fromCode($parsed->phase);

        foreach ($parsed->items as $item) {
            $created = BoqItem::query()->create([
                'organization_id' => $organizationId,
                'bill_of_quantity_id' => $boq->id,
                'boq_section_id' => $item['section_ref'] !== null ? ($sectionMap[$item['section_ref']] ?? null) : null,
                'reference_no' => $item['ref'],
                'type' => BoqItemType::tryFrom($item['type']) ?? BoqItemType::Standard,
                'status' => BoqItemStatus::Imported,
                'short_text' => $item['short_text'],
                'long_text' => $item['long_text'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['total_price'],
                'is_addendum' => $item['is_addendum'],
                'external_id' => $item['external_id'],
                'position' => $item['position'],
            ]);

            if ($item['unit_price'] !== null || $item['total_price'] !== null) {
                BoqItemPriceSnapshot::query()->create([
                    'boq_item_id' => $created->id,
                    'gaeb_import_id' => $import->id,
                    'phase' => $phase,
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'captured_at' => $created->freshTimestamp(),
                ]);
            }
        }
    }

    /**
     * Prüft, ob ein Reimport Positionen mit Ausführungs-/Abrechnungsbezug
     * berühren würde, und bricht in dem Fall ab.
     */
    private function guardReimport(BillOfQuantity $target, ParsedBoq $parsed): void {
        $incomingRefs = array_column($parsed->items, 'ref');

        /** @var list<string> $protected */
        $protected = $target->items()
            ->get(['reference_no', 'status'])
            ->filter(fn (BoqItem $item): bool => $item->status->hasExecutionReference())
            ->pluck('reference_no')
            ->all();

        $touched = array_values(array_intersect($protected, $incomingRefs));

        if ($touched !== []) {
            throw new BoqImportConflictException($touched);
        }
    }
}
