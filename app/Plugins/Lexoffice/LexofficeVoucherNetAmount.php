<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeVoucherNetAmount.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

use App\Models\LexofficeVoucher;
use CommonToolkit\ValueObjects\Money;

/**
 * Nettobetrag eines Lexoffice-Belegs. Die voucherlist liefert ausschließlich
 * Brutto (totalAmount/openAmount); Kundenkonten rechnen aber netto. Der Wert
 * wird einmalig per Beleg-Detailabruf geholt und auf dem Beleg gecacht.
 *
 * Kein Netto ermittelbar (Beleg ist keine Rechnung, API-Fehler) ⇒ null; der
 * Aufrufer entscheidet, ob er auf Brutto zurückfällt oder die Buchung auslässt.
 */
class LexofficeVoucherNetAmount {
    public function __construct(private readonly LexofficeInvoiceService $invoices) {}

    public function for(LexofficeVoucher $voucher): ?Money {
        if ($voucher->net_amount !== null) {
            return $voucher->net_amount;
        }

        if (! $this->invoices->isConfigured() || $voucher->external_id === '') {
            return null;
        }

        try {
            $payload = $this->invoices->fetchInvoice($voucher->external_id);
        } catch (\Throwable) {
            return null;
        }

        $totalPrice = $payload['totalPrice'] ?? null;
        if (! \is_array($totalPrice) || ! isset($totalPrice['totalNetAmount'])) {
            return null;
        }

        $net = Money::ofFloat((float) $totalPrice['totalNetAmount'], $voucher->currency);
        $voucher->net_amount = $net;
        $voucher->saveQuietly();

        return $net;
    }

    /**
     * Auf den Nettoanteil heruntergerechneter Zahlbetrag: bei Vollzahlung der
     * Nettobetrag selbst, bei Teilzahlung proportional zum gezahlten Brutto.
     * Ohne bekanntes Netto bleibt es beim Bruttoanteil (dokumentierter Fallback
     * — besser eine leicht zu hohe Zahlung als gar keine).
     */
    public function paidNet(LexofficeVoucher $voucher, Money $paidGross, Money $totalGross): Money {
        $net = $this->for($voucher);
        if ($net === null || ! $totalGross->isPositive()) {
            return $paidGross;
        }

        if ($paidGross->compareTo($totalGross) >= 0) {
            return $net;
        }

        return $net->times($paidGross->toFloat() / $totalGross->toFloat());
    }
}
