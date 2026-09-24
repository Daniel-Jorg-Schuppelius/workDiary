<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceGaebExporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Contracts;

use App\Models\Invoicing\Invoice;
use ERechnungToolkit\Enums\GaebPhase;

/**
 * Rechnung als GAEB-Datei (Feature 049/108): gebunden vom Baumodul
 * ({@see \App\Services\Gaeb\GaebInvoiceExportService}); Null-Bindung wirft
 * {@see \App\Modules\ModuleUnavailableException}.
 */
interface InvoiceGaebExporter {
    /** @return array{content: string, filename: string, losses: list<string>} */
    public function export(Invoice $invoice, GaebPhase $phase = GaebPhase::Invoice): array;
}
