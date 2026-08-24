<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceItemObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Observers;

use App\Models\InvoiceItem;
use App\Services\Invoicing\InvoiceItemReleaseService;

/**
 * Beim Löschen einer Rechnungsposition die Quellposten freigeben (F14) —
 * die Logik lebt im {@see InvoiceItemReleaseService}, damit Query-Delete-
 * Pfade sie explizit aufrufen können (Eloquent-Events feuern dort nicht).
 */
class InvoiceItemObserver {
    public function __construct(private readonly InvoiceItemReleaseService $release) {}

    public function saving(InvoiceItem $i): void {
        // MVP-416: Zeilennetto inkl. Positionsrabatt (Prozent XOR Betrag).
        $i->amount = \App\Services\Invoicing\InvoiceTotalsCalculator::lineNet(
            (float) $i->quantity,
            $i->unit_price,
            $i->discount_percent,
            $i->discount_amount,
            $i->invoice->currency ?? \CommonToolkit\Enums\CurrencyCode::Euro,
            // Zeilenbetrag in Spaltenpräzision (2 NK) — der Einzelpreis
            // rechnet mit seinen 4 NK, gespeichert wird auf Cent gerundet.
        )->withScale(2);
    }

    public function deleting(InvoiceItem $item): void {
        $this->release->releaseSources($item);
    }
}
