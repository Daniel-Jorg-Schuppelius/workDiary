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
use CommonToolkit\Helper\Data\CryptoHelper;
use CommonToolkit\ValueObjects\Money;
use ERechnungToolkit\Entities\Gaeb\{GaebBoq, GaebTotals};
use ERechnungToolkit\Parsers\GaebDaXmlParser;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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
        $fileHash = CryptoHelper::hash($xml);

        try {
            $parsed = $this->parser->parse($xml);
        } catch (InvalidArgumentException $e) {
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
                'gaeb_version' => $parsed->getVersion(),
                'phase' => GaebPhase::fromCode($parsed->getPhaseCode()),
                'status' => GaebImportStatus::PreflightFailed,
                'section_count' => $parsed->countSections(),
                'item_count' => $parsed->countItems(),
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
                'name' => $options['name'] ?? ($parsed->getProjectName() ?? $filename),
                'external_id' => $parsed->getExternalId(),
                'gaeb_version' => $parsed->getVersion(),
                'up_components' => $this->mapUpComponents($parsed),
                'totals' => $this->mapTotals($parsed->getTotals()),
                'phase' => GaebPhase::fromCode($parsed->getPhaseCode()),
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
                'gaeb_version' => $parsed->getVersion(),
                'phase' => GaebPhase::fromCode($parsed->getPhaseCode()),
                'status' => GaebImportStatus::Imported,
                'section_count' => $parsed->countSections(),
                'item_count' => $parsed->countItems(),
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
    private function persistSections(BillOfQuantity $boq, int $organizationId, GaebBoq $parsed): array {
        $map = [];
        foreach ($parsed->getSections() as $section) {
            $parentRef = $section->getParentReference();
            $created = BoqSection::query()->create([
                'organization_id' => $organizationId,
                'bill_of_quantity_id' => $boq->id,
                'parent_id' => $parentRef !== null ? ($map[$parentRef] ?? null) : null,
                'reference_no' => $section->getReference(),
                'label' => $section->getLabel(),
                'external_id' => $section->getExternalId(),
                'totals' => $this->mapTotals($section->getTotals()),
                'position' => $section->getPosition(),
            ]);
            $map[$section->getReference()] = $created->id;
        }

        return $map;
    }

    /**
     * @param array<string, int> $sectionMap
     */
    private function persistItems(BillOfQuantity $boq, GaebImport $import, int $organizationId, GaebBoq $parsed, array $sectionMap): void {
        $phase = GaebPhase::fromCode($parsed->getPhaseCode());

        foreach ($parsed->getItems() as $item) {
            $sectionRef = $item->getSectionReference();
            $subDescriptions = [];
            foreach ($item->getSubDescriptions() as $sub) {
                $subDescriptions[] = ['no' => $sub->getNo(), 'quantity' => $sub->getQuantity(), 'unit' => $sub->getUnit()];
            }
            $complements = [];
            foreach ($item->getTextComplements() as $complement) {
                $complements[] = [
                    'mark' => $complement->getMark(),
                    'kind' => $complement->getKind(),
                    'caption' => $complement->getCaption(),
                    'body' => $complement->getBody(),
                    'tail' => $complement->getTail(),
                ];
            }

            $created = BoqItem::query()->create([
                'organization_id' => $organizationId,
                'bill_of_quantity_id' => $boq->id,
                'boq_section_id' => $sectionRef !== null ? ($sectionMap[$sectionRef] ?? null) : null,
                'reference_no' => $item->getReference(),
                // Beide Enums teilen dieselben Werte; das Toolkit kennt die Labels nicht.
                'type' => BoqItemType::from($item->getType()->value),
                'provision_kind' => $item->getProvisionKind(),
                'alternative_group' => $item->getAlternativeGroup(),
                'alternative_no' => $item->getAlternativeNo(),
                'markup_type' => $item->getMarkupType(),
                'status' => BoqItemStatus::Imported,
                'short_text' => $item->getShortText(),
                'long_text' => $item->getLongText(),
                'sub_descriptions' => $subDescriptions === [] ? null : $subDescriptions,
                'text_complements' => $complements === [] ? null : $complements,
                'quantity' => $item->getQuantity(),
                'unit' => $item->getUnit(),
                'unit_price' => $item->getUnitPrice(),
                'unit_price_components' => $this->shares($item->getUnitPriceComponents()),
                'total_price' => $item->getTotalPrice(),
                'is_addendum' => $item->isAddendum(),
                'change_order_no' => $item->getChangeOrderNo(),
                'change_order_status' => $item->getChangeOrderStatus()?->value,
                'not_offered' => $item->isNotOffered(),
                'not_applicable' => $item->isNotApplicable(),
                'free_quantity' => $item->hasFreeQuantity(),
                'hourly_item' => $item->isHourlyItem(),
                'discount_percent' => $item->getDiscountPercent(),
                'vat_rate' => $item->getVatRate(),
                'bidder_comment' => $item->getBidderComment(),
                'alternative_bid_status' => $item->getAlternativeBidStatus()?->value,
                'external_id' => $item->getExternalId(),
                'position' => $item->getPosition(),
            ]);

            if ($item->getUnitPrice() !== null || $item->getTotalPrice() !== null) {
                BoqItemPriceSnapshot::query()->create([
                    'boq_item_id' => $created->id,
                    'gaeb_import_id' => $import->id,
                    'phase' => $phase,
                    'unit_price' => $item->getUnitPrice(),
                    'total_price' => $item->getTotalPrice(),
                    'captured_at' => $created->freshTimestamp(),
                ]);
            }
        }
    }

    /**
     * EP-Anteilsvorgaben des Auftraggebers als schlichte Struktur ablegen.
     *
     * @return list<array{no: int, label: ?string, category: ?string}>|null
     */
    private function mapUpComponents(GaebBoq $parsed): ?array {
        $components = [];
        foreach ($parsed->getUpComponents() as $component) {
            $components[] = ['no' => $component->getNo(), 'label' => $component->getLabel(), 'category' => $component->getCategory()];
        }

        return $components === [] ? null : $components;
    }

    /**
     * Summen mit Nachlass unverändert übernehmen — nachgerechnet wird bewusst
     * nicht, die gelieferten Werte sind der Stand der Gegenseite.
     *
     * @return array<string, string|null>|null
     */
    private function mapTotals(?GaebTotals $totals): ?array {
        if ($totals === null) {
            return null;
        }

        return [
            'total' => $totals->getTotal()?->getAmount(),
            'discount_percent' => $totals->getDiscountPercent(),
            'discount_amount' => $totals->getDiscountAmount()?->getAmount(),
            'total_after_discount' => $totals->getTotalAfterDiscount()?->getAmount(),
            'vat_rate' => $totals->getVatRate(),
            'total_net' => $totals->getTotalNet()?->getAmount(),
            'vat_amount' => $totals->getVatAmount()?->getAmount(),
            'total_gross' => $totals->getTotalGross()?->getAmount(),
        ];
    }

    /**
     * Einheitspreisanteile als Dezimalstrings ins JSON-Feld — Money bleibt der
     * Typ im Toolkit, das Modell speichert die Zahl.
     *
     * @param  list<Money>  $shares
     * @return list<string>|null
     */
    private function shares(array $shares): ?array {
        if ($shares === []) {
            return null;
        }

        return array_map(static fn (Money $share): string => $share->getAmount(), $shares);
    }

    /**
     * Prüft, ob ein Reimport Positionen mit Ausführungs-/Abrechnungsbezug
     * berühren würde, und bricht in dem Fall ab.
     */
    private function guardReimport(BillOfQuantity $target, GaebBoq $parsed): void {
        $incomingRefs = [];
        foreach ($parsed->getItems() as $item) {
            $incomingRefs[] = $item->getReference();
        }

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
