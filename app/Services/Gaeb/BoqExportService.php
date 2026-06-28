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

/**
 * GAEB-Export inkl. Audit (Feature 049, MVP-085): erzeugt den GAEB-Stand über
 * den {@see GaebDaXmlExporter} und protokolliert ihn mit Inhalts-Hash in
 * `boq_exports`. Der Generator ist deterministisch, daher ist derselbe LV-Stand
 * reproduzierbar (gleicher Hash).
 */
class BoqExportService {
    public function __construct(private readonly GaebDaXmlExporter $exporter) {}

    /**
     * @return array{xml: string, export: BoqExport}
     */
    public function export(BillOfQuantity $boq, GaebPhase $phase, ?int $createdBy = null): array {
        $xml = $this->exporter->export($boq, $phase);

        $export = BoqExport::query()->create([
            'organization_id' => $boq->organization_id,
            'bill_of_quantity_id' => $boq->id,
            'phase' => $phase,
            'gaeb_version' => '3.3',
            'file_hash' => hash('sha256', $xml),
            'item_count' => $boq->items()->count(),
            'created_by' => $createdBy,
        ]);

        return ['xml' => $xml, 'export' => $export];
    }

    /** Reiner Inhalts-Hash ohne Protokollierung (z. B. für Idempotenzprüfungen). */
    public function contentHash(BillOfQuantity $boq, GaebPhase $phase): string {
        return hash('sha256', $this->exporter->export($boq, $phase));
    }
}
