<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucherSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Feed\Sources;

use App\Enums\Billing\{DocumentDirection, DocumentKind, DocumentOrigin};
use App\Enums\Finance\AccountingVoucherState;
use App\Models\Finance\AccountingVoucher;
use App\Models\Invoicing\Invoice;
use App\Services\Billing\DocumentFeedFilters;
use App\Services\Billing\Feed\{DocumentFeedSource, FeedProjection, MarksLinkedExpenses, SuppressesCoreInvoices, SuppressesCoreInvoicesByNumber};
use App\Support\MorphMap;
use App\Support\Query\DateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Gespiegelte Belege aus `accounting_vouchers` (Feature 122, MVP-611;
 * anbieterneutral seit MVP-731).
 *
 * Das sind die Belege, die DIREKT in der Buchhaltung entstanden sind —
 * Kassenbon, per Mail eingegangene Lieferantenrechnung. Ohne sie hat der
 * Belegfluss ein Loch, das niemand sieht. Die Tabelle ist Kern-Schema
 * (Kern-Migration), deshalb bleibt die Quelle im Kern.
 *
 * Richtung, Belegart und Zustand liest die Projektion aus den normalisierten
 * Spalten, die jeder Puller füllt. Für Zeilen, die vor MVP-731 gespiegelt
 * wurden, greift der alte sevDesk-Pfad als Fallback (C/D + Statuszahlen) —
 * eine Backfill-Migration hätte Daten umgeschrieben, die das Fremdsystem
 * führt, und beim nächsten Lauf ohnehin überschrieben werden.
 *
 * Dublettenregel 1 (MVP-1495): Eine Rechnungsnummer vergibt ein Unternehmer
 * nur einmal (§ 14 Abs. 4 Nr. 4 UStG). Ein ausgehender Spiegelbeleg mit der
 * Nummer einer lokalen Rechnung ist deshalb dieselbe Rechnung, wenn er
 * demselben Kunden gehört oder die Rechnung nachweislich an dieses Programm
 * übertragen wurde (Verknüpfung der Belegvergabe, sevDesk und easybill). Ein
 * Beleg eines anderen Kunden verdrängt nichts. Dublettenregel 3 (MVP-1498):
 * die bestätigte Auslagen-Verknüpfung mit einem Spiegelbeleg.
 */
class AccountingVoucherSource implements DocumentFeedSource, MarksLinkedExpenses, SuppressesCoreInvoices {
    use SuppressesCoreInvoicesByNumber;

    public function key(): string {
        return 'accounting_voucher';
    }

    /**
     * Plugin-ID je Herkunftssystem (MVP-1031): Die Beleg-Abrufe schreiben ihre
     * Plugin-ID, und die Werte von {@see DocumentOrigin} sind die Plugin-IDs —
     * kein Anbieter muss hier stehen.
     *
     * @return array<string, DocumentOrigin>
     */
    private function origins(): array {
        $origins = [];
        foreach (DocumentOrigin::cases() as $origin) {
            if ($origin !== DocumentOrigin::Local) {
                $origins[$origin->value] = $origin;
            }
        }

        return $origins;
    }

    public function builder(DocumentFeedFilters $f): ?Builder {
        if (! $f->allows('voucher')) {
            return null;
        }

        $origins = array_filter(
            $this->origins(),
            static fn (DocumentOrigin $origin): bool => $f->wantsOrigin($origin),
        );
        if ($origins === []) {
            return null;
        }

        /** @var array<string, string> $originMap */
        $originMap = array_map(static fn (DocumentOrigin $o): string => $o->value, $origins);
        $origin = FeedProjection::caseMap('accounting_vouchers.plugin_id', $originMap, DocumentOrigin::Local->value);

        // Normalisierte Spalten zuerst; C/D bleibt Fallback für Altzeilen
        // (sevDesk: C = Ausgabe/eingehend, D = Einnahme/ausgehend).
        $legacyDirection = FeedProjection::caseMap('accounting_vouchers.voucher_type', [
            'C' => DocumentDirection::Incoming->value,
            'D' => DocumentDirection::Outgoing->value,
        ], DocumentDirection::Neutral->value);
        $direction = "COALESCE(accounting_vouchers.direction, $legacyDirection)";
        $kind = "COALESCE(accounting_vouchers.document_kind, '" . DocumentKind::Other->value . "')";
        // sevDesk-Statuscodes sind Zahlen; PHP macht daraus int-Schlüssel,
        // die caseMap() nicht annimmt. Deshalb explizit als String-Paare.
        /** @var array<string, string> $statusMap */
        $statusMap = array_combine(
            ['50', '100', '750', '1000'],
            ['draft', 'open', 'open', 'paid'],
        );
        $legacyState = FeedProjection::caseMap('accounting_vouchers.voucher_status', $statusMap, 'open');
        $state = "COALESCE(accounting_vouchers.voucher_state, $legacyState)";
        // Vorzeichen nach Vorgangsart wie bei Lexoffice und lokalen Rechnungen; die Richtung trennt
        // Umsatz und Aufwand schon in der Summe. Entwurf, Storniert und im Fremdsystem gelöscht zählen
        // nicht. Beträge als Betrag: Programme führen Gutschriften teils negativ (MVP-1495).
        /** @var array<string, string> $kindSigns */
        $kindSigns = [];
        foreach (DocumentKind::cases() as $case) {
            $kindSigns[$case->value] = (string) $case->sign();
        }
        $sign = 'CASE WHEN accounting_vouchers.source_deleted_at IS NOT NULL THEN 0'
            . " WHEN $state IN ('" . AccountingVoucherState::Draft->value . "', '" . AccountingVoucherState::Cancelled->value . "') THEN 0"
            . ' ELSE ' . FeedProjection::caseMap($kind, $kindSigns, '1', quoted: false) . ' END';

        return DB::table('accounting_vouchers')
            ->selectRaw(FeedProjection::columns([
                "'voucher' AS source_type",
                'accounting_vouchers.id AS source_id',
                'accounting_vouchers.id AS link_id',
                "$origin AS origin",
                "$direction AS direction",
                "$kind AS kind",
                "$sign AS sign",
                "COALESCE(accounting_vouchers.voucher_number, '') AS number",
                'accounting_vouchers.voucher_date AS doc_date',
                'accounting_vouchers.due_date AS due_on',
                // Im Fremdsystem gelöscht: eigener Zustand, archiviert, nichts mehr offen.
                "CASE WHEN accounting_vouchers.source_deleted_at IS NOT NULL THEN 'deleted' ELSE $state END AS state",
                'CASE WHEN accounting_vouchers.archived OR accounting_vouchers.source_deleted_at IS NOT NULL THEN 1 ELSE 0 END AS is_archived',
                "CASE WHEN accounting_vouchers.customer_id IS NOT NULL THEN 'customer'
                    WHEN accounting_vouchers.supplier_id IS NOT NULL THEN 'supplier' ELSE NULL END AS contact_type",
                'COALESCE(accounting_vouchers.customer_id, accounting_vouchers.supplier_id) AS contact_id',
                'COALESCE(
                    (SELECT customers.name FROM customers WHERE customers.id = accounting_vouchers.customer_id),
                    (SELECT suppliers.name FROM suppliers WHERE suppliers.id = accounting_vouchers.supplier_id)
                ) AS contact_name',
                '0 AS dunning_level',
                'ABS(COALESCE(accounting_vouchers.total_amount, 0)) AS amount_gross',
                "CASE WHEN $state = 'open' AND accounting_vouchers.source_deleted_at IS NULL
                    THEN ABS(COALESCE(accounting_vouchers.open_amount, accounting_vouchers.total_amount, 0)) ELSE 0 END AS open_amount",
                'accounting_vouchers.currency AS currency',
                'NULL AS project_id',
                'accounting_vouchers.foreign_amount AS foreign_amount',
                'accounting_vouchers.foreign_currency AS foreign_currency',
            ]))
            ->where('accounting_vouchers.organization_id', $f->organizationId)
            ->whereIn('accounting_vouchers.plugin_id', array_keys($origins))
            // Eine Serienvorlage ist kein Beleg; sie gehört der Planquelle (wie in der Akte).
            ->where(static fn (Builder $q) => $q->whereNull('accounting_vouchers.document_kind')->orWhere('accounting_vouchers.document_kind', '!=', DocumentKind::RecurringTemplate->value))
            ->whereNotNull('accounting_vouchers.voucher_date')
            ->whereBetween('accounting_vouchers.voucher_date', DateRange::days($f->from, $f->to));
    }

    public function suppressCoreInvoices(Builder $invoices): void {
        $this->suppressByMirroredNumber($invoices, 'accounting_vouchers', 'voucher_number', static function (Builder $voucher): void {
            $voucher->whereNull('accounting_vouchers.source_deleted_at')
                ->where(static fn (Builder $q) => $q
                    ->where('accounting_vouchers.direction', DocumentDirection::Outgoing->value)
                    ->orWhere(static fn (Builder $w) => $w->whereNull('accounting_vouchers.direction')->where('accounting_vouchers.voucher_type', 'D')))
                ->where(static fn (Builder $q) => $q
                    ->whereColumn('accounting_vouchers.customer_id', 'invoices.customer_id')
                    ->orWhereExists(static fn (Builder $transfer) => $transfer->select(DB::raw(1))
                        ->from('external_references')
                        ->whereColumn('external_references.organization_id', 'invoices.organization_id')
                        ->whereColumn('external_references.plugin_id', 'accounting_vouchers.plugin_id')
                        ->where('external_references.referenceable_type', MorphMap::alias(Invoice::class))
                        ->whereColumn('external_references.referenceable_id', 'invoices.id')));
        });
    }

    public function expenseLinkCriteria(): array {
        return array_values(array_map(
            static fn (DocumentOrigin $origin): array => ['plugin_id' => $origin->value, 'external_type' => AccountingVoucher::EXPENSE_LINK],
            $this->origins(),
        ));
    }
}
