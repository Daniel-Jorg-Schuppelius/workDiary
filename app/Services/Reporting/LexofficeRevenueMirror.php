<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeRevenueMirror.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Reporting;

use App\Models\{Invoice, LexofficeVoucher};
use Illuminate\Support\Collection;

/**
 * Umsatz aus dem Lexoffice-Beleg-Spiegel (Phase-54-Nachtrag): Bei externer
 * Rechnungshoheit existieren die Rechnungen nur im Buchhaltungsprogramm —
 * `lexoffice_vouchers` liefert sie je Kunde in die Auswertungen nach
 * (Billing-Umsatz, Kundenwert-Fakturiert).
 *
 * Regeln: Rechnungen/Abschläge positiv, Gutschriften negativ; Entwürfe und
 * stornierte Belege zählen nicht. Belege, deren Nummer eine lokale Rechnung
 * trägt (`invoices.number`/`external_number` — von der App übergebene
 * Rechnungen übernehmen die Lexoffice-Nummer), werden übersprungen: keine
 * Doppelzählung mit der lokalen Fakturierung.
 */
class LexofficeRevenueMirror {
    /**
     * Rechnungsähnliche Spiegelbelege für den Zahlungsverhaltens-Report
     * (Phase-54-Nachtrag): gleiche Zeilenform wie lokale Rechnungen.
     * `paid_date` stammt aus der Payments-Anreicherung des Belegsyncs —
     * bezahlte Belege OHNE nachgeladenes Datum fallen aus den
     * Zahldauer-Statistiken heraus (ehrlich, statt geraten) und gelten in
     * der DSO-Historie als geschlossen. Gutschriften bleiben hier außen
     * vor (keine Fälligkeits-/Zahlungssemantik).
     *
     * @param  list<int>  $excludedCustomerIds
     * @return list<array{id:?int, customerId:int, number:string, issuedOn:?string, dueOn:?string, paidOn:?string, total:float, paid:bool}>
     */
    public function invoiceRows(string $upTo, ?int $customerId = null, array $excludedCustomerIds = []): array {
        /** @var Collection<int, LexofficeVoucher> $vouchers */
        $vouchers = LexofficeVoucher::query()
            ->whereNotNull('customer_id')
            ->whereNotNull('voucher_date')
            ->where('voucher_date', '<=', $upTo)
            ->whereIn('voucher_type', ['invoice', 'downpaymentinvoice'])
            ->whereNotIn('voucher_status', ['draft', 'voided'])
            ->when($customerId !== null, fn($q) => $q->where('customer_id', $customerId))
            ->when($excludedCustomerIds !== [], fn($q) => $q->whereNotIn('customer_id', $excludedCustomerIds))
            ->get(['customer_id', 'voucher_status', 'voucher_number', 'voucher_date', 'due_date', 'paid_date', 'total_amount']);

        if ($vouchers->isEmpty()) {
            return [];
        }

        $knownNumbers = $this->knownLocalNumbers();

        $rows = [];
        foreach ($vouchers as $voucher) {
            $number = (string) $voucher->voucher_number;
            if ($number !== '' && isset($knownNumbers[$number])) {
                continue;
            }
            $rows[] = [
                'id' => null,
                'customerId' => (int) $voucher->customer_id,
                'number' => $number,
                'issuedOn' => $voucher->voucher_date?->toDateString(),
                'dueOn' => $voucher->due_date?->toDateString(),
                'paidOn' => $voucher->paid_date?->toDateString(),
                'total' => $voucher->total_amount?->toFloat() ?? 0.0,
                'paid' => $voucher->voucher_status === 'paid',
            ];
        }

        return $rows;
    }

    /**
     * @param  list<int>  $excludedCustomerIds
     * @return array<int, array{count:int, total:float}> customerId → Aggregat
     */
    public function perCustomer(string $from, string $to, ?int $customerId = null, array $excludedCustomerIds = []): array {
        /** @var Collection<int, LexofficeVoucher> $vouchers */
        $vouchers = LexofficeVoucher::query()
            ->whereNotNull('customer_id')
            ->whereBetween('voucher_date', [$from, $to])
            ->whereIn('voucher_type', ['invoice', 'downpaymentinvoice', 'creditnote'])
            ->whereNotIn('voucher_status', ['draft', 'voided'])
            ->when($customerId !== null, fn($q) => $q->where('customer_id', $customerId))
            ->when($excludedCustomerIds !== [], fn($q) => $q->whereNotIn('customer_id', $excludedCustomerIds))
            ->get(['customer_id', 'voucher_type', 'voucher_number', 'total_amount']);

        if ($vouchers->isEmpty()) {
            return [];
        }

        $knownNumbers = $this->knownLocalNumbers();

        /** @var array<int, array{count:int, total:float}> $agg */
        $agg = [];
        foreach ($vouchers as $voucher) {
            $number = (string) $voucher->voucher_number;
            if ($number !== '' && isset($knownNumbers[$number])) {
                continue;
            }
            $cid = (int) $voucher->customer_id;
            $sign = $voucher->voucher_type === 'creditnote' ? -1.0 : 1.0;
            $agg[$cid] ??= ['count' => 0, 'total' => 0.0];
            $agg[$cid]['count']++;
            $agg[$cid]['total'] += $sign * ($voucher->total_amount?->toFloat() ?? 0.0);
        }

        return $agg;
    }

    /**
     * Dedup-Anker: alle lokalen Rechnungsnummern der Org (number +
     * external_number) — von der App an Lexoffice übergebene Rechnungen
     * übernehmen die Lexoffice-Belegnummer und dürfen nicht doppelt zählen.
     *
     * @return array<string, true>
     */
    private function knownLocalNumbers(): array {
        $knownNumbers = [];
        Invoice::query()
            ->get(['number', 'external_number'])
            ->each(function (Invoice $inv) use (&$knownNumbers): void {
                foreach ([(string) $inv->number, (string) $inv->external_number] as $number) {
                    if ($number !== '') {
                        $knownNumbers[$number] = true;
                    }
                }
            });

        return $knownNumbers;
    }
}
