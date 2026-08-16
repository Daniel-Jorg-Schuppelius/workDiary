<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VoucherTypes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Support\Billing;

use App\Enums\Billing\{DocumentDirection, DocumentKind, DocumentOrigin};

/**
 * Einzige Quelle für die fachliche Einordnung von Lexoffice-`voucherType`-Werten
 * (Feature 105, MVP-542).
 *
 * Vor dieser Klasse lag dieselbe Klassifikation in fünf Kopien verstreut
 * (LexofficeRevenueMirror, Supplier{Analysis,Value}ReportBuilder,
 * RetainerVoucherReconciler, LexofficeDunningService). Die Mengen-Konstanten
 * unten sind deshalb **verhaltensgleich** zu den abgelösten Listen — die
 * feinere Einordnung über {@see self::classify()} ist zusätzlich, nicht
 * ersetzend.
 */
final class VoucherTypes {
    /**
     * Geldwirksame Verkaufsbelege (Erlös). OHNE `downpaymentdeduction`:
     * die Abschlagsverrechnung steckt bereits in der Schlussrechnung und
     * würde den Umsatz doppelt mindern.
     *
     * @var list<string>
     */
    public const REVENUE = ['invoice', 'salesinvoice', 'downpaymentinvoice'];

    /**
     * Erlösbelege inklusive der mindernden Gutschriften — für Aggregate, die
     * mit Vorzeichen rechnen ({@see self::sign()}).
     *
     * @var list<string>
     */
    public const REVENUE_WITH_CREDITS = ['invoice', 'salesinvoice', 'downpaymentinvoice', 'creditnote', 'salescreditnote'];

    /**
     * Verkaufsrechnungen im engeren Sinn: mahnbar, als Kundenrechnung einer
     * Pauschale zuordenbar.
     *
     * @var list<string>
     */
    public const SALES_INVOICES = ['invoice', 'salesinvoice'];

    /**
     * Einkaufsbelege im Lexoffice-Spiegel (`supplier_id` gesetzt); `voucher`
     * ist der generische Ausgabebeleg ohne eigene Belegart.
     *
     * @var list<string>
     */
    public const EXPENSES = ['purchaseinvoice', 'purchasecreditnote', 'voucher'];

    /**
     * Belegarten, die eine Ausgabe mindern.
     *
     * @var list<string>
     */
    public const EXPENSE_CREDITS = ['purchasecreditnote'];

    /**
     * Belegstatus ohne Geldwirkung — Entwurf ist noch nicht gebucht, Storno
     * nicht mehr.
     *
     * @var list<string>
     */
    public const IGNORED_STATUSES = ['draft', 'voided'];

    /**
     * voucherType → [Richtung, Vorgangsart].
     *
     * @var array<string, array{DocumentDirection, DocumentKind}>
     */
    private const MAP = [
        'invoice' => [DocumentDirection::Outgoing, DocumentKind::Invoice],
        'salesinvoice' => [DocumentDirection::Outgoing, DocumentKind::Invoice],
        'downpaymentinvoice' => [DocumentDirection::Outgoing, DocumentKind::DownPayment],
        'downpaymentdeduction' => [DocumentDirection::Outgoing, DocumentKind::DownPaymentDeduction],
        'creditnote' => [DocumentDirection::Outgoing, DocumentKind::CreditNote],
        'salescreditnote' => [DocumentDirection::Outgoing, DocumentKind::CreditNote],
        'purchaseinvoice' => [DocumentDirection::Incoming, DocumentKind::Invoice],
        'purchasecreditnote' => [DocumentDirection::Incoming, DocumentKind::CreditNote],
        'voucher' => [DocumentDirection::Incoming, DocumentKind::Other],
        'quotation' => [DocumentDirection::Neutral, DocumentKind::Quote],
        'orderconfirmation' => [DocumentDirection::Neutral, DocumentKind::OrderConfirmation],
        'deliverynote' => [DocumentDirection::Neutral, DocumentKind::DeliveryNote],
    ];

    /**
     * Ordnet einen Lexoffice-Belegtyp ein. Unbekannte Typen gelten als
     * neutraler Sonstiger Beleg — sie sollen sichtbar sein, aber niemals
     * stillschweigend in eine Geldsumme fließen.
     */
    public static function classify(?string $voucherType): DocumentClassification {
        [$direction, $kind] = self::MAP[(string) $voucherType] ?? [DocumentDirection::Neutral, DocumentKind::Other];

        return new DocumentClassification(DocumentOrigin::Lexoffice, $direction, $kind);
    }

    /** Vorzeichen eines Belegtyps für Geldsummen (0 ohne Geldwirkung). */
    public static function sign(?string $voucherType): int {
        return self::classify($voucherType)->sign();
    }

    /**
     * Alle Belegtypen einer Richtung — für Filter über den Lexoffice-Spiegel.
     *
     * @return list<string>
     */
    public static function ofDirection(DocumentDirection $direction): array {
        $types = [];
        foreach (self::MAP as $type => [$mapped, $kind]) {
            if ($mapped === $direction) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * Alle Belegtypen einer Vorgangsart.
     *
     * @return list<string>
     */
    public static function ofKind(DocumentKind $kind): array {
        $types = [];
        foreach (self::MAP as $type => [$direction, $mapped]) {
            if ($mapped === $kind) {
                $types[] = $type;
            }
        }

        return $types;
    }
}
