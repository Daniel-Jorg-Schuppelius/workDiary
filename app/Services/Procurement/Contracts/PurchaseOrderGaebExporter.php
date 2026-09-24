<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderGaebExporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement\Contracts;

use App\Models\Procurement\PurchaseOrder;
use ERechnungToolkit\Enums\GaebPhase;

/**
 * Bestellung als GAEB-Datei (MVP-863): definiert vom Einkauf, gebunden vom
 * Baumodul ({@see \App\Services\Gaeb\GaebOrderExportService}). Null-Bindung
 * wirft {@see \App\Modules\ModuleUnavailableException}.
 */
interface PurchaseOrderGaebExporter {
    /** @return array{content: string, filename: string, losses: list<string>} */
    public function export(PurchaseOrder $order, GaebPhase $phase = GaebPhase::Order): array;
}
