<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqExportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Enums\Gaeb\GaebPhase;
use App\Models\{BillOfQuantity, BoqExport};
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use CommonToolkit\Helper\Data\CryptoHelper;
use ERechnungToolkit\Entities\Gaeb\GaebParty;
use ERechnungToolkit\Enums\{GaebFormat, GaebPhase as ToolkitPhase};
use ERechnungToolkit\Generators\GaebWriter;

/**
 * GAEB-Export inkl. Audit (Feature 049, MVP-085; Feature 108): erzeugt den
 * GAEB-Stand über den Generator des erechnung-toolkits und protokolliert ihn
 * mit Inhalts-Hash in `boq_exports`. Der Generator ist deterministisch, daher
 * ist derselbe LV-Stand reproduzierbar (gleicher Hash) — dafür ist das feste
 * Erzeugungsdatum nötig.
 */
class BoqExportService {
    /** Festes Datum im GAEBInfo, damit derselbe LV-Stand denselben Hash ergibt. */
    private const EXPORT_DATE = '2026-01-01';

    public function __construct(
        private readonly BoqDocumentFactory $documents,
        private readonly GaebWriter $writer,
        private readonly XRechnungGenerator $einvoice,
        private readonly GaebPreflight $preflight,
    ) {}

    /**
     * Prüfliste vor der Abgabe (MVP-619), ohne zu exportieren: nimmt vorweg, was
     * ava-sign beim Reimport prüft.
     *
     * @return array{ok: bool, errors: list<string>, warnings: list<string>, meta: array<string, mixed>}
     */
    public function preflight(BillOfQuantity $boq, GaebPhase $phase): array {
        return $this->preflight->checkForExport(
            $this->documents->fromModel($boq, $phase),
            $phase,
            $this->contractor($boq) !== null,
        );
    }

    /**
     * @return array{xml: string, export: BoqExport}
     */
    public function export(BillOfQuantity $boq, GaebPhase $phase, ?int $createdBy = null, ?GaebFormat $format = null): array {
        $target = $format ?? $this->sourceFormat($boq);
        $written = $this->render($boq, $phase, $target);

        $export = BoqExport::query()->create([
            'organization_id' => $boq->organization_id,
            'bill_of_quantity_id' => $boq->id,
            'phase' => $phase,
            'gaeb_version' => '3.3',
            'format' => $target->value,
            // Was die Wandlung gekostet hat, gehört ins Protokoll (D6).
            'losses' => $written['losses'] === [] ? null : $written['losses'],
            'file_hash' => CryptoHelper::hash($written['content']),
            'item_count' => $boq->items()->count(),
            'created_by' => $createdBy,
        ]);

        return ['xml' => $written['content'], 'losses' => $written['losses'], 'export' => $export];
    }

    /**
     * Herkunftsformat des LV. Die Vergabestelle erwartet zurück, was sie
     * herausgegeben hat; ohne Vermerk bleibt es bei DA XML.
     */
    public function sourceFormat(BillOfQuantity $boq): GaebFormat {
        return GaebFormat::tryFrom((string) $boq->source_format) ?? GaebFormat::DaXml;
    }

    /** Reiner Inhalts-Hash ohne Protokollierung (z. B. für Idempotenzprüfungen). */
    public function contentHash(BillOfQuantity $boq, GaebPhase $phase, ?GaebFormat $format = null): string {
        return CryptoHelper::hash($this->render($boq, $phase, $format ?? $this->sourceFormat($boq))['content']);
    }

    /** @return array{content: string, losses: list<string>} */
    private function render(BillOfQuantity $boq, GaebPhase $phase, GaebFormat $format): array {
        return $this->writer->write(
            $this->documents->fromModel($boq, $phase),
            $format,
            ToolkitPhase::from($phase->value),
            self::EXPORT_DATE,
            $this->contractor($boq),
        );
    }

    /**
     * Eigene Anschrift als GAEB-Auftragnehmer (`CTR`). Das Schema verlangt sie
     * in X84, X86 und X87 vollständig; Quelle sind die E-Rechnungs-Stammdaten
     * der Organisation, damit es keine zweite Absenderpflege gibt. Fehlt ein
     * Pflichtfeld, bleibt `CTR` weg — die Lücke gehört in den Preflight, nicht
     * in eine erfundene Adresse.
     */
    private function contractor(BillOfQuantity $boq): ?GaebParty {
        $seller = $this->einvoice->sellerDataFor($boq->organization);

        if ($seller['name'] === '' || $seller['street'] === '' || $seller['zip'] === '' || $seller['city'] === '') {
            return null;
        }

        return new GaebParty(
            $seller['name'],
            $seller['street'],
            $seller['zip'],
            $seller['city'],
            $seller['country'] !== 'CH' && $seller['country'] !== 'GB',
        );
    }
}
