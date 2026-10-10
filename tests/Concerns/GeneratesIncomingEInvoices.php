<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GeneratesIncomingEInvoices.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Invoicing\Invoice;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;

/**
 * Eingangs-Fixtures über den eigenen Ausgangs-Generator: Die Organisation
 * stellt die Rechnung als Verkäufer aus und tritt danach als Käufer auf.
 * Sonst erkennt der Rechnungseingang (MVP-1107) eine Kopie der eigenen
 * Ausgangsrechnung.
 */
trait GeneratesIncomingEInvoices {
    /** USt-IdNr. der Organisation in ihrer Rolle als Rechnungsempfänger. */
    protected const BUYER_VAT_ID = 'DE811907980';

    /** @var array<string, mixed>|null */
    private ?array $fixtureSellerSettings = null;

    protected function incomingXml(Invoice $invoice): string {
        $this->fixtureSellerSettings ??= (array) ($this->organization->fresh()?->settings['einvoice'] ?? []);
        $this->organization->update(['settings' => ['einvoice' => $this->fixtureSellerSettings]]);

        $xml = app(XRechnungGenerator::class)->generate($invoice->refresh()->load(['items', 'customer']));

        $this->organization->update(['settings' => ['einvoice' => ['vat_id' => self::BUYER_VAT_ID]]]);

        return $xml;
    }
}
