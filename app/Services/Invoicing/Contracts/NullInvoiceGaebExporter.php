<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullInvoiceGaebExporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Contracts;

use App\Models\Invoicing\Invoice;
use App\Modules\ModuleUnavailableException;
use ERechnungToolkit\Enums\GaebPhase;

final class NullInvoiceGaebExporter implements InvoiceGaebExporter {
    public function export(Invoice $invoice, GaebPhase $phase = GaebPhase::Invoice): array {
        throw ModuleUnavailableException::for('gaeb');
    }
}
