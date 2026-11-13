<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GiroCodeCheck.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\GiroCode;

use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Billing\Sepa\PaymentProposalService;
use App\Support\Crypto\BlindIndex;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\{BankHelper, CryptoHelper, NumberHelper, StringHelper};
use Illuminate\Support\Facades\DB;

/**
 * Gegenprobe des GiroCodes (Feature 208, MVP-1663): IBAN-Prüfziffer, Betrag
 * gegen das Brutto der Erkennung, Name gegen den Lieferanten — und vor allem
 * die IBAN gegen die erkannte Rechnungs-IBAN und die Bankverbindungen des
 * Lieferanten. Eine abweichende IBAN ist ein bekanntes Betrugsmuster: sie
 * warnt im Zuordnungsdialog und sperrt die Rechnung im Zahllauf, bis eine
 * Person mit Freigaberecht sie auditiert bestätigt.
 *
 * Die Bestätigung gilt für genau die bestätigte Lage (GiroCode-IBAN,
 * Rechnungs-IBAN, Lieferant): Ändert sich eines davon, sperrt der Zahllauf
 * wieder. Der Fingerabdruck liegt im verschlüsselten GiroCode-Feld.
 *
 * Die Prüfung rechnet bei jedem Aufruf neu — Werte erfassen, Zuordnen und
 * Stammdaten ändern wirken sofort, ohne dass ein gespeichertes Ergebnis
 * veraltet.
 */
final class GiroCodeCheck {
    /** GiroCode-IBAN ≠ erkannte bzw. erfasste Rechnungs-IBAN (sperrt). */
    public const string IBAN_DIFFERS_INVOICE = 'iban_differs_invoice';

    /** GiroCode-IBAN gehört zu keiner Bankverbindung des Lieferanten (sperrt). */
    public const string IBAN_DIFFERS_SUPPLIER = 'iban_differs_supplier';

    /** Zwei GiroCodes mit verschiedener IBAN auf einem Beleg (sperrt). */
    public const string MULTIPLE_CODES = 'multiple_codes';

    /**
     * Die Rechnungs-IBAN stammt aus dem GiroCode, und es gibt keine
     * hinterlegte Bankverbindung, mit der sie sich vergleichen ließe
     * (Lieferant unbekannt oder ohne Bankverbindung): eine neue
     * Bankverbindung, die eine Person bestätigen muss (sperrt).
     */
    public const string NEW_BANK_DETAILS = 'new_bank_details';

    /** Prüfziffer der GiroCode-IBAN falsch — die IBAN wird nicht übernommen. */
    public const string IBAN_INVALID = 'iban_invalid';

    public const string AMOUNT_DIFFERS = 'amount_differs';

    public const string NAME_DIFFERS = 'name_differs';

    /** Kein GiroCode lesbar (Werkzeug fehlt, Datei zu groß, Lesefehler). */
    public const string UNREADABLE = 'unreadable';

    /** QR-Code mit EPC-Kennung, aber ungültiger Nutzlast. */
    public const string INVALID = 'invalid';

    /** @var list<string> */
    public const array BLOCKING = [self::IBAN_DIFFERS_INVOICE, self::IBAN_DIFFERS_SUPPLIER, self::MULTIPLE_CODES, self::NEW_BANK_DETAILS];

    /** Ab dieser Ähnlichkeit (0–1) gilt der Empfängername als derselbe Lieferant. */
    private const float NAME_SIMILARITY = 0.6;

    /**
     * Befunde der Gegenprobe, sperrende zuerst.
     *
     * @param  Supplier|null  $supplier  Lieferant; ohne Angabe derselbe wie im Zahlungsvorschlag (zugeordnet, sonst über USt-IdNr. bzw. Name)
     * @return list<string>
     */
    public function findings(IncomingEInvoice $incoming, ?Supplier $supplier = null): array {
        $reading = (array) $incoming->girocode;
        $status = $reading['status'] ?? null;
        if ($status === GiroCodeReader::UNREADABLE) {
            return [self::UNREADABLE];
        }
        if ($status === GiroCodeReader::INVALID) {
            return [self::INVALID];
        }
        $iban = $status === GiroCodeReader::FOUND ? BankHelper::normalizeIBAN(is_string($reading['iban'] ?? null) ? $reading['iban'] : null) : null;
        if ($iban === null) {
            return [];
        }
        $supplier ??= $this->supplierOf($incoming);

        $findings = [];
        $invoiceIban = BankHelper::normalizeIBAN(trim((string) $incoming->creditor_iban));
        if ($invoiceIban !== null && $invoiceIban !== $iban) {
            $findings[] = self::IBAN_DIFFERS_INVOICE;
        }
        if ($supplier !== null && $this->differsFromSupplier($iban, $supplier)) {
            $findings[] = self::IBAN_DIFFERS_SUPPLIER;
        }
        if (($reading['conflicting'] ?? false) === true) {
            $findings[] = self::MULTIPLE_CODES;
        }
        if (self::ibanFromCode($incoming) && ($supplier === null || (! $supplier->is_collective && $this->knownIbanHashes($supplier) === []))) {
            $findings[] = self::NEW_BANK_DETAILS;
        }
        if (($reading['iban_valid'] ?? false) !== true) {
            $findings[] = self::IBAN_INVALID;
        }
        if ($this->amountDiffers($incoming, $reading)) {
            $findings[] = self::AMOUNT_DIFFERS;
        }
        if ($this->nameDiffers($incoming, $supplier, $reading)) {
            $findings[] = self::NAME_DIFFERS;
        }

        return $findings;
    }

    /** Eine sperrende Abweichung liegt vor (bestätigt oder nicht). */
    public function deviates(IncomingEInvoice $incoming, ?Supplier $supplier = null): bool {
        return array_intersect($this->findings($incoming, $supplier), self::BLOCKING) !== [];
    }

    /** Sperrt den Zahllauf: Abweichung ohne gültige Bestätigung. */
    public function requiresConfirmation(IncomingEInvoice $incoming, ?Supplier $supplier = null): bool {
        return $this->deviates($incoming, $supplier) && ! $this->isConfirmed($incoming, $supplier);
    }

    /** Bestätigt für die aktuelle Lage (GiroCode-IBAN, Rechnungs-IBAN, Lieferant). */
    public function isConfirmed(IncomingEInvoice $incoming, ?Supplier $supplier = null): bool {
        $confirmed = ((array) $incoming->girocode)['confirmed_for'] ?? null;

        return $incoming->girocode_confirmed_at !== null
            && is_string($confirmed)
            && hash_equals($confirmed, $this->fingerprint($incoming, $supplier ?? $this->supplierOf($incoming)));
    }

    /**
     * Abweichung bestätigen: Zeitpunkt, Person und Fingerabdruck der Lage,
     * auditiert ohne IBAN im Klartext (nur die letzten vier Stellen).
     */
    public function confirm(IncomingEInvoice $incoming, User $actor, ?Supplier $supplier = null): void {
        DB::transaction(function () use ($incoming, $actor, $supplier): void {
            $incoming->newQueryWithoutScopes()->whereKey($incoming->getKey())->lockForUpdate()->first();
            $incoming->refresh();
            $supplier ??= $this->supplierOf($incoming);
            $findings = $this->findings($incoming, $supplier);
            $reading = (array) $incoming->girocode;
            $incoming->forceFill([
                'girocode' => [...$reading, 'confirmed_for' => $this->fingerprint($incoming, $supplier)],
                'girocode_confirmed_at' => now(),
                'girocode_confirmed_by' => $actor->id,
            ])->save();
            $incoming->audit('incoming_einvoice.girocode_confirmed', [
                'findings' => array_values(array_intersect($findings, self::BLOCKING)),
                'girocode_iban_tail' => substr((string) ($reading['iban'] ?? ''), -4),
                'invoice_iban_tail' => substr((string) $incoming->creditor_iban, -4),
                'supplier_id' => $supplier?->id,
            ]);
        });
    }

    /** IBAN für die Anzeige: Land und Prüfziffern, Rest bis auf die letzten vier Stellen verdeckt. */
    public static function maskIban(?string $iban): string {
        $iban = BankHelper::normalizeIBAN($iban);
        if ($iban === null) {
            return '—';
        }

        return substr($iban, 0, 4) . ' •••• ' . substr($iban, -4);
    }

    /**
     * Fingerabdruck der bestätigten Lage: GiroCode-IBAN, Rechnungs-IBAN,
     * Lieferant. Liegt nur verschlüsselt im GiroCode-Feld.
     */
    public function fingerprint(IncomingEInvoice $incoming, ?Supplier $supplier = null): string {
        $reading = (array) $incoming->girocode;
        $supplier ??= $this->supplierOf($incoming);

        return (string) CryptoHelper::hash(implode('|', [
            BankHelper::normalizeIBAN(is_string($reading['iban'] ?? null) ? $reading['iban'] : null) ?? '',
            BankHelper::normalizeIBAN(trim((string) $incoming->creditor_iban)) ?? '',
            (string) $supplier?->id,
        ]));
    }

    /** Die Rechnungs-IBAN kam aus dem GiroCode (Herkunft `filled_iban`) und steht unverändert so da. */
    public static function ibanFromCode(IncomingEInvoice $incoming): bool {
        $reading = (array) $incoming->girocode;
        $code = BankHelper::normalizeIBAN(is_string($reading['iban'] ?? null) ? $reading['iban'] : null);

        return ($reading['filled_iban'] ?? false) === true
            && $code !== null
            && $code === BankHelper::normalizeIBAN(trim((string) $incoming->creditor_iban));
    }

    /**
     * Derselbe Lieferant wie im Zahlungsvorschlag (N4): zugeordnet, sonst
     * über USt-IdNr. bzw. Name — sonst zeigte die Detailseite eine andere Lage
     * als der Zahllauf.
     */
    private function supplierOf(IncomingEInvoice $incoming): ?Supplier {
        return app(PaymentProposalService::class)->supplierFor($incoming);
    }

    /** @return list<string> */
    private function knownIbanHashes(Supplier $supplier): array {
        return array_values(array_filter(
            $supplier->bankAccounts()->withoutGlobalScopes()->whereNotNull('iban_hash')->pluck('iban_hash')->all(),
            'is_string',
        ));
    }

    /** Wie {@see \App\Services\Billing\Sepa\PaymentProposalService::ibanDiffersFromMaster()}: alle Bankverbindungen zählen; der Sammellieferant hat keine. */
    private function differsFromSupplier(string $iban, Supplier $supplier): bool {
        if ($supplier->is_collective) {
            return true;
        }
        $known = $this->knownIbanHashes($supplier);

        return $known !== [] && array_intersect($known, BlindIndex::ibanCandidates($iban)) === [];
    }

    /** @param  array<string, mixed>  $reading */
    private function amountDiffers(IncomingEInvoice $incoming, array $reading): bool {
        $amount = $reading['amount'] ?? null;
        $gross = $incoming->amount_gross;
        if (! is_string($amount) || ! is_numeric($amount) || $gross === null) {
            return false;
        }
        if ($incoming->currency !== null && $incoming->currency !== CurrencyCode::Euro) {
            return true;
        }

        return NumberHelper::comparePrecise($amount, $gross->getAmount(), 2) !== 0;
    }

    /** @param  array<string, mixed>  $reading */
    private function nameDiffers(IncomingEInvoice $incoming, ?Supplier $supplier, array $reading): bool {
        $name = IncomingEInvoice::nameKey(is_string($reading['name'] ?? null) ? $reading['name'] : null);
        $candidates = array_values(array_filter(array_map(
            IncomingEInvoice::nameKey(...),
            [$supplier?->name, $supplier?->company, $incoming->seller_name],
        )));
        if ($name === null || $candidates === []) {
            return false;
        }
        foreach ($candidates as $candidate) {
            if (str_contains($candidate, $name) || str_contains($name, $candidate)
                || StringHelper::similarity($candidate, $name) >= self::NAME_SIMILARITY) {
                return false;
            }
        }

        return true;
    }
}
