<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentFeedQuery.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Billing;

use App\Enums\Billing\{DocumentDirection, DocumentKind, DocumentOrigin};
use App\Enums\Expense\ExpenseStatus;
use App\Models\{Expense, IncomingEInvoice, Invoice};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Support\Billing\VoucherTypes;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ein Belegfluss über sechs Quellen (Feature 105, MVP-543/654).
 *
 * Angebote, lokale Rechnungen, gespiegelte Lexoffice-Belege, gespiegelte
 * orgaMAX-Rechnungen, Eingangsrechnungen und Auslagen werden auf eine
 * gemeinsame Zeilenform projiziert und per `UNION ALL` zusammengeführt —
 * Sortierung, Filterung und Aggregation laufen danach in SQL. Ein
 * Zusammenführen in PHP würde bei sechs paginierten Quellen weder sortieren
 * noch summieren können.
 *
 * Die Projektion trägt ein vorberechnetes `sign` (−1/0/+1): geldwirksame
 * Vorzeichen und Nicht-Wirksamkeit (Entwurf, Storno, Angebot) stecken damit in
 * der Zeile selbst, und jede Summe ist `SUM(sign * amount_gross)`.
 */
class DocumentFeedQuery {
    /** Sortierbare Spalten der Projektion → Feed-Spalte. */
    public const SORTS = [
        'number' => 'number',
        'date' => 'doc_date',
        'kind' => 'kind',
        'contact' => 'contact_name',
        'state' => 'state',
        'due' => 'due_on',
        'amount' => 'amount_gross',
    ];

    public function __construct(private readonly DocumentFeedFilters $filters) {}

    /** @return LengthAwarePaginator<int, object> */
    public function paginate(int $perPage, string $sort, string $dir): LengthAwarePaginator {
        $column = self::SORTS[$sort] ?? 'doc_date';
        $direction = $dir === 'asc' ? 'asc' : 'desc';

        /** @var LengthAwarePaginator<int, object> $page */
        $page = $this->filtered()
            ->orderBy($column, $direction)
            // Stabile Reihenfolge über Quellen hinweg: gleiche Datumswerte
            // sonst je Abfrage anders sortiert (UNION garantiert nichts).
            ->orderByDesc('doc_date')
            ->orderBy('source_type')
            ->orderByDesc('source_id')
            ->paginate($perPage)
            ->withQueryString();

        return $page;
    }

    /**
     * Kennzahlen je Währung über die **gesamte** gefilterte Menge.
     *
     * Keine Umrechnung: eine Zeile je vorkommender Währung. Auslagen bleiben
     * getrennt, weil eine Auslage und die später eintreffende
     * Lieferantenrechnung ohne bestätigte Verknüpfung derselbe Aufwand sein
     * können (Dublettenregel 3).
     *
     * @return list<array{currency: string, revenue: float, expense: float, internal: float,
     *     internalPending: float, balance: float, open: float, openCount: int,
     *     overdue: float, overdueCount: int, neutralCount: int}>
     */
    public function totals(): array {
        $today = Carbon::today()->toDateString();
        $expense = "source_type = 'expense'";

        $rows = $this->filtered()
            ->selectRaw('currency')
            ->selectRaw("SUM(CASE WHEN direction = 'outgoing' THEN sign * amount_gross ELSE 0 END) AS revenue")
            ->selectRaw("SUM(CASE WHEN direction = 'incoming' AND NOT ($expense) THEN sign * amount_gross ELSE 0 END) AS expense_total")
            ->selectRaw("SUM(CASE WHEN $expense THEN sign * amount_gross ELSE 0 END) AS internal_total")
            ->selectRaw("SUM(CASE WHEN $expense AND state = 'open' THEN amount_gross ELSE 0 END) AS internal_pending")
            ->selectRaw("SUM(CASE WHEN state = 'open' THEN open_amount ELSE 0 END) AS open_total")
            // Grundmenge zur Überfälligkeit: „7 Belege" beantwortet erst mit
            // dem Nenner die Frage, wovon sieben.
            ->selectRaw("SUM(CASE WHEN state = 'open' THEN 1 ELSE 0 END) AS open_count")
            ->selectRaw("SUM(CASE WHEN state = 'open' AND due_on IS NOT NULL AND due_on < ? THEN open_amount ELSE 0 END) AS overdue_total", [$today])
            ->selectRaw("SUM(CASE WHEN state = 'open' AND due_on IS NOT NULL AND due_on < ? THEN 1 ELSE 0 END) AS overdue_count", [$today])
            ->selectRaw("SUM(CASE WHEN direction = 'neutral' THEN 1 ELSE 0 END) AS neutral_count")
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        return array_values($rows->map(static fn(object $row): array => [
            'currency' => (string) $row->currency,
            'revenue' => (float) $row->revenue,
            'expense' => (float) $row->expense_total,
            'internal' => (float) $row->internal_total,
            'internalPending' => (float) $row->internal_pending,
            'balance' => (float) $row->revenue - (float) $row->expense_total,
            'open' => (float) $row->open_total,
            'openCount' => (int) $row->open_count,
            'overdue' => (float) $row->overdue_total,
            'overdueCount' => (int) $row->overdue_count,
            'neutralCount' => (int) $row->neutral_count,
        ])->all());
    }

    /**
     * Zeilenzahl je Vorgangsart und Richtung für die Tab-Zähler — ein Lauf
     * statt eines Zählers je Tab.
     *
     * @return array<string, int> Schlüssel `<direction>:<kind>` und `overdue`
     */
    public function tabCounts(): array {
        $today = Carbon::today()->toDateString();

        $rows = $this->filtered(ignoreKindAndDirection: true)
            ->selectRaw('direction, kind, COUNT(*) AS row_count')
            ->selectRaw("SUM(CASE WHEN state = 'open' AND due_on IS NOT NULL AND due_on < ? THEN 1 ELSE 0 END) AS overdue_count", [$today])
            ->groupBy('direction', 'kind')
            ->get();

        $counts = ['overdue' => 0];
        foreach ($rows as $row) {
            $counts[$row->direction . ':' . $row->kind] = (int) $row->row_count;
            $counts['overdue'] += (int) $row->overdue_count;
        }

        return $counts;
    }

    /**
     * Gefilterte Projektion. Art-, Richtungs- und Statusfilter greifen außen
     * auf den normalisierten Spalten — die Quellen selbst filtern nur, was
     * ihre Indizes bedienen können (Organisation, Zeitraum, Sichtbarkeit).
     */
    private function filtered(bool $ignoreKindAndDirection = false): Builder {
        $f = $this->filters;
        $query = DB::query()->fromSub($this->union(), 'feed');

        if (! $ignoreKindAndDirection) {
            if ($f->kindValues() !== []) {
                $query->whereIn('kind', $f->kindValues());
            }
            if ($f->directionValues() !== []) {
                $query->whereIn('direction', $f->directionValues());
            }
        }

        if ($f->origin !== null) {
            $query->where('origin', $f->origin->value);
        }

        if ($f->contactType !== null && $f->contactType !== '') {
            $query->where('contact_type', $f->contactType);
        }

        if ($f->customerId !== null) {
            $query->where('contact_type', 'customer')->where('contact_id', $f->customerId);
        }

        if ($f->state !== null && $f->state !== '') {
            $query->where('state', $f->state);
        }

        if (! $f->includeArchived) {
            $query->where('is_archived', 0);
        }

        if ($f->onlyOverdue) {
            $query->where('state', 'open')
                ->whereNotNull('due_on')
                ->where('due_on', '<', Carbon::today()->toDateString());
        }

        if ($f->search !== '') {
            // Deutsche Betragseingabe (1.167,08) → 1167.08 für den Spaltenvergleich.
            $amount = str_replace(',', '.', str_replace(['.', ' '], '', $f->search));
            $datePatterns = $this->dateLikePatterns($f->search);

            $query->where(function (Builder $q) use ($f, $amount, $datePatterns): void {
                $q->whereLikeEscaped('number', $f->search)
                    ->orWhereLikeEscaped('contact_name', $f->search);

                if (is_numeric($amount)) {
                    $q->orWhereLikeEscaped('amount_gross', $amount);
                }

                foreach ($datePatterns as $pattern) {
                    $q->orWhere('doc_date', 'like', $pattern);
                }
            });
        }

        return $query;
    }

    /**
     * Übersetzt eine deutsche/ISO/teilweise Datumseingabe in LIKE-Muster gegen
     * die als `Y-m-d` gespeicherte Datumsspalte. Unterstützt `29.06.2026`,
     * `06.2026`, `2026`, `29.06` (jahresunabhängig) sowie ISO.
     *
     * @return list<string>
     */
    private function dateLikePatterns(string $search): array {
        $s = trim($search);

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $s, $m)) {
            return [sprintf('%04d-%02d-%02d%%', (int) $m[3], (int) $m[2], (int) $m[1])];
        }
        if (preg_match('/^(\d{1,2})\.(\d{4})$/', $s, $m)) {
            return [sprintf('%04d-%02d%%', (int) $m[2], (int) $m[1])];
        }
        if (preg_match('/^(\d{4})$/', $s, $m)) {
            return [sprintf('%04d%%', (int) $m[1])];
        }
        if (preg_match('/^(\d{1,2})\.(\d{1,2})$/', $s, $m)) {
            return [sprintf('%%-%02d-%02d', (int) $m[2], (int) $m[1])];
        }
        if (preg_match('/^\d{4}-\d{2}(-\d{2})?$/', $s)) {
            return [$s . '%'];
        }

        return [];
    }

    /** Vereinigung aller Quellen, die der Filter überhaupt zulässt. */
    private function union(): Builder {
        $parts = array_values(array_filter([
            $this->invoices(),
            $this->quotes(),
            $this->vouchers(),
            $this->accountingVouchers(),
            $this->orgamaxInvoices(),
            $this->incomingEInvoices(),
            $this->expenses(),
        ]));

        if ($parts === []) {
            // Kein Sub-Select passt zum Filter: leere, aber spaltengleiche Menge.
            return $this->emptySet();
        }

        $union = array_shift($parts);
        foreach ($parts as $part) {
            $union->unionAll($part);
        }

        return $union;
    }

    /**
     * Lokale Rechnungen. An die Buchhaltung übergebene Rechnungen übernehmen
     * deren Belegnummer — sie erscheinen dann nur einmal, und zwar als
     * externer Beleg (Dublettenregel 1: extern führt).
     */
    private function invoices(): ?Builder {
        $f = $this->filters;
        if (! $f->allows('invoice') || ! $f->wantsOrigin(DocumentOrigin::Local) || ! $f->wantsFixed(DocumentDirection::Outgoing)) {
            return null;
        }

        $kind = $this->caseMap('invoices.type', [
            Invoice::TYPE_CREDIT_NOTE => DocumentKind::CreditNote->value,
            Invoice::TYPE_CANCELLATION => DocumentKind::Cancellation->value,
            Invoice::TYPE_DOWN_PAYMENT => DocumentKind::DownPayment->value,
            Invoice::TYPE_PARTIAL => DocumentKind::DownPayment->value,
        ], DocumentKind::Invoice->value);

        $state = $this->caseMap('invoices.status', [
            Invoice::STATUS_DRAFT => 'draft',
            Invoice::STATUS_PAID => 'paid',
            Invoice::STATUS_CANCELLED => 'cancelled',
        ], 'open');

        // Retainer-Pauschalen sind bewusst nicht erlöswirksam (Feature 098):
        // die Buchhaltung finalisiert sie, die lokale Zeile ist nur Nachweis.
        $sign = "CASE
            WHEN invoices.status IN ('" . Invoice::STATUS_DRAFT . "', '" . Invoice::STATUS_CANCELLED . "') THEN 0
            WHEN invoices.type = '" . Invoice::TYPE_RETAINER . "' THEN 0
            WHEN invoices.type IN ('" . Invoice::TYPE_CREDIT_NOTE . "', '" . Invoice::TYPE_CANCELLATION . "') THEN -1
            ELSE 1 END";

        // Über das Modell statt DB::table(): `invoices` ist
        // festschreibungspflichtig, Roh-Tabellenzugriffe sind dort gesperrt
        // (GobdLockGuardRuleTest). toBase() liefert den Query-Builder
        // inklusive Organisations-Scope.
        $query = Invoice::query()->toBase()
            ->selectRaw($this->projection([
                "'invoice' AS source_type",
                'invoices.id AS source_id',
                'invoices.id AS link_id',
                "'" . DocumentOrigin::Local->value . "' AS origin",
                "'" . DocumentDirection::Outgoing->value . "' AS direction",
                "$kind AS kind",
                "$sign AS sign",
                'invoices.number AS number',
                'COALESCE(invoices.issued_on, DATE(invoices.created_at)) AS doc_date',
                'invoices.due_on AS due_on',
                "$state AS state",
                '0 AS is_archived',
                "'customer' AS contact_type",
                'invoices.customer_id AS contact_id',
                '(SELECT customers.name FROM customers WHERE customers.id = invoices.customer_id) AS contact_name',
                'COALESCE(invoices.dunning_level, 0) AS dunning_level',
                'COALESCE(invoices.total, 0) AS amount_gross',
                "CASE WHEN $state = 'open' THEN COALESCE(invoices.total, 0) ELSE 0 END AS open_amount",
                'invoices.currency AS currency',
            ]))
            ->where('invoices.organization_id', $f->organizationId)
            ->whereBetween(DB::raw('COALESCE(invoices.issued_on, DATE(invoices.created_at))'), [$f->from->toDateString(), $f->to->toDateString()])
            ->whereNotExists(function (Builder $sub): void {
                $sub->select(DB::raw(1))
                    ->from('lexoffice_vouchers')
                    ->whereColumn('lexoffice_vouchers.organization_id', 'invoices.organization_id')
                    ->where(function (Builder $q): void {
                        $q->whereColumn('lexoffice_vouchers.voucher_number', 'invoices.number')
                            ->orWhereColumn('lexoffice_vouchers.voucher_number', 'invoices.external_number');
                    });
            })
            // Dieselbe Regel gegenüber dem orgaMAX-Spiegel (MVP-670).
            ->whereNotExists(function (Builder $sub): void {
                $sub->select(DB::raw(1))
                    ->from('orgamax_invoices')
                    ->whereColumn('orgamax_invoices.organization_id', 'invoices.organization_id')
                    ->where(function (Builder $q): void {
                        $q->whereColumn('orgamax_invoices.invoice_number', 'invoices.number')
                            ->orWhereColumn('orgamax_invoices.invoice_number', 'invoices.external_number');
                    });
            });

        return $query;
    }

    /** Angebote — ohne Geldwirkung, aber Teil des Vorgangsflusses. */
    private function quotes(): ?Builder {
        $f = $this->filters;
        if (! $f->allows('quote') || ! $f->wantsOrigin(DocumentOrigin::Local) || ! $f->wantsFixed(DocumentDirection::Neutral, DocumentKind::Quote)) {
            return null;
        }

        $state = $this->caseMap('quotes.status', [
            'draft' => 'draft',
            'accepted' => 'paid',
            'partially_accepted' => 'paid',
            'rejected' => 'cancelled',
            'expired' => 'cancelled',
        ], 'open');

        return DB::table('quotes')
            ->selectRaw($this->projection([
                "'quote' AS source_type",
                'quotes.id AS source_id',
                'quotes.id AS link_id',
                "'" . DocumentOrigin::Local->value . "' AS origin",
                "'" . DocumentDirection::Neutral->value . "' AS direction",
                "'" . DocumentKind::Quote->value . "' AS kind",
                '0 AS sign',
                'quotes.number AS number',
                'DATE(quotes.created_at) AS doc_date',
                'quotes.valid_until AS due_on',
                "$state AS state",
                '0 AS is_archived',
                "'customer' AS contact_type",
                'quotes.customer_id AS contact_id',
                '(SELECT customers.name FROM customers WHERE customers.id = quotes.customer_id) AS contact_name',
                '0 AS dunning_level',
                'COALESCE(quotes.total, 0) AS amount_gross',
                '0 AS open_amount',
                "'" . $this->defaultCurrency() . "' AS currency",
            ]))
            ->where('quotes.organization_id', $f->organizationId)
            ->whereBetween(DB::raw('DATE(quotes.created_at)'), [$f->from->toDateString(), $f->to->toDateString()]);
    }

    /** Gespiegelte Belege des führenden Buchhaltungssystems. */
    private function vouchers(): ?Builder {
        $f = $this->filters;
        if (! $f->allows('voucher') || ! $f->wantsOrigin(DocumentOrigin::Lexoffice)) {
            return null;
        }

        $directionCases = [];
        $kindCases = [];
        $signCases = [];
        // array_merge, nicht `+`: bei Listen würde der Plus-Operator die
        // gleichindizierten Elemente der Folgearrays verwerfen.
        $allTypes = array_merge(
            VoucherTypes::ofDirection(DocumentDirection::Outgoing),
            VoucherTypes::ofDirection(DocumentDirection::Incoming),
            VoucherTypes::ofDirection(DocumentDirection::Neutral),
        );

        foreach ($allTypes as $type) {
            $class = VoucherTypes::classify($type);
            $directionCases[$type] = $class->direction->value;
            $kindCases[$type] = $class->kind->value;
            $signCases[$type] = (string) $class->sign();
        }

        $direction = $this->caseMap('lexoffice_vouchers.voucher_type', $directionCases, DocumentDirection::Neutral->value);
        $kind = $this->caseMap('lexoffice_vouchers.voucher_type', $kindCases, DocumentKind::Other->value);
        $signMap = $this->caseMap('lexoffice_vouchers.voucher_type', $signCases, '0', quoted: false);

        $ignored = "'" . implode("', '", VoucherTypes::IGNORED_STATUSES) . "'";
        $sign = "CASE WHEN lexoffice_vouchers.voucher_status IN ($ignored) THEN 0 ELSE $signMap END";

        $state = $this->caseMap('lexoffice_vouchers.voucher_status', [
            'draft' => 'draft',
            'voided' => 'cancelled',
            'rejected' => 'cancelled',
            'paid' => 'paid',
            'paidoff' => 'paid',
            'checked' => 'paid',
            'transferred' => 'paid',
            'accepted' => 'paid',
        ], 'open');

        return DB::table('lexoffice_vouchers')
            ->selectRaw($this->projection([
                "'voucher' AS source_type",
                'lexoffice_vouchers.id AS source_id',
                'lexoffice_vouchers.id AS link_id',
                "'" . DocumentOrigin::Lexoffice->value . "' AS origin",
                "$direction AS direction",
                "$kind AS kind",
                "$sign AS sign",
                "COALESCE(lexoffice_vouchers.voucher_number, '') AS number",
                'lexoffice_vouchers.voucher_date AS doc_date',
                'lexoffice_vouchers.due_date AS due_on',
                "$state AS state",
                'CASE WHEN lexoffice_vouchers.archived THEN 1 ELSE 0 END AS is_archived',
                "CASE WHEN lexoffice_vouchers.customer_id IS NOT NULL THEN 'customer'
                    WHEN lexoffice_vouchers.supplier_id IS NOT NULL THEN 'supplier' ELSE NULL END AS contact_type",
                'COALESCE(lexoffice_vouchers.customer_id, lexoffice_vouchers.supplier_id) AS contact_id',
                'COALESCE(
                    (SELECT customers.name FROM customers WHERE customers.id = lexoffice_vouchers.customer_id),
                    (SELECT suppliers.name FROM suppliers WHERE suppliers.id = lexoffice_vouchers.supplier_id)
                ) AS contact_name',
                '0 AS dunning_level',
                'COALESCE(lexoffice_vouchers.total_amount, 0) AS amount_gross',
                "CASE WHEN $state = 'open'
                    THEN COALESCE(lexoffice_vouchers.open_amount, lexoffice_vouchers.total_amount, 0) ELSE 0 END AS open_amount",
                'lexoffice_vouchers.currency AS currency',
            ]))
            ->where('lexoffice_vouchers.organization_id', $f->organizationId)
            ->whereNotNull('lexoffice_vouchers.voucher_date')
            ->whereBetween('lexoffice_vouchers.voucher_date', [$f->from->toDateString(), $f->to->toDateString()]);
    }

    /**
     * Gespiegelte Belege aus `accounting_vouchers` (Feature 122, MVP-611).
     *
     * Das sind die Belege, die DIREKT in der Buchhaltung entstanden sind —
     * Kassenbon, per Mail eingegangene Lieferantenrechnung. Ohne sie hat der
     * Belegfluss ein Loch, das niemand sieht. sevDesk kennt nur
     * Einnahme/Ausgabe (`creditDebit`), keine Belegart-Taxonomie: daraus wird
     * die Richtung abgeleitet, die Art bleibt „sonstiges".
     */
    private function accountingVouchers(): ?Builder {
        $f = $this->filters;
        if (! $f->allows('voucher') || ! $f->wantsOrigin(DocumentOrigin::SevDesk)) {
            return null;
        }

        // C = Einnahme (Ausgangsrichtung), D = Ausgabe (Eingangsrichtung).
        $direction = $this->caseMap('accounting_vouchers.voucher_type', [
            'C' => DocumentDirection::Outgoing->value,
            'D' => DocumentDirection::Incoming->value,
        ], DocumentDirection::Neutral->value);
        $sign = $this->caseMap('accounting_vouchers.voucher_type', [
            'C' => '1',
            'D' => '-1',
        ], '0', quoted: false);
        // sevDesk-Statuscodes sind Zahlen; PHP macht daraus int-Schlüssel,
        // die caseMap() nicht annimmt. Deshalb explizit als String-Paare.
        /** @var array<string, string> $statusMap */
        $statusMap = array_combine(
            ['50', '100', '750', '1000'],
            ['draft', 'open', 'open', 'paid'],
        );
        $state = $this->caseMap('accounting_vouchers.voucher_status', $statusMap, 'open');

        return DB::table('accounting_vouchers')
            ->selectRaw($this->projection([
                "'voucher' AS source_type",
                'accounting_vouchers.id AS source_id',
                'accounting_vouchers.id AS link_id',
                "'" . DocumentOrigin::SevDesk->value . "' AS origin",
                "$direction AS direction",
                "'" . DocumentKind::Other->value . "' AS kind",
                "$sign AS sign",
                "COALESCE(accounting_vouchers.voucher_number, '') AS number",
                'accounting_vouchers.voucher_date AS doc_date',
                'accounting_vouchers.due_date AS due_on',
                "$state AS state",
                'CASE WHEN accounting_vouchers.archived THEN 1 ELSE 0 END AS is_archived',
                "CASE WHEN accounting_vouchers.customer_id IS NOT NULL THEN 'customer'
                    WHEN accounting_vouchers.supplier_id IS NOT NULL THEN 'supplier' ELSE NULL END AS contact_type",
                'COALESCE(accounting_vouchers.customer_id, accounting_vouchers.supplier_id) AS contact_id',
                'COALESCE(
                    (SELECT customers.name FROM customers WHERE customers.id = accounting_vouchers.customer_id),
                    (SELECT suppliers.name FROM suppliers WHERE suppliers.id = accounting_vouchers.supplier_id)
                ) AS contact_name',
                '0 AS dunning_level',
                'COALESCE(accounting_vouchers.total_amount, 0) AS amount_gross',
                "CASE WHEN $state = 'open'
                    THEN COALESCE(accounting_vouchers.open_amount, accounting_vouchers.total_amount, 0) ELSE 0 END AS open_amount",
                'accounting_vouchers.currency AS currency',
            ]))
            ->where('accounting_vouchers.organization_id', $f->organizationId)
            ->whereNotNull('accounting_vouchers.voucher_date')
            ->whereBetween('accounting_vouchers.voucher_date', [$f->from->toDateString(), $f->to->toDateString()]);
    }

    /**
     * Gespiegelte orgaMAX-Rechnungen (MVP-670). Gleiche Rolle wie
     * {@see self::vouchers()}, nur mit der Belegsemantik des anderen Systems:
     * orgaMAX führt ausschließlich Ausgangsbelege, Wiederholungs-*Vorlagen*
     * sind kein Beleg und bleiben draußen.
     */
    private function orgamaxInvoices(): ?Builder {
        $f = $this->filters;
        if (! $f->allows('voucher') || ! $f->wantsOrigin(DocumentOrigin::OrgaMax) || ! $f->wantsFixed(DocumentDirection::Outgoing)) {
            return null;
        }

        // Abschlagsrechnung ist eine Anzahlung, Schluss- und
        // Wiederholungsrechnung sind gewöhnliche Rechnungen.
        $kind = $this->caseMap('orgamax_invoices.invoice_type', [
            'depositInvoice' => DocumentKind::DownPayment->value,
        ], DocumentKind::Invoice->value);

        $state = $this->caseMap('orgamax_invoices.invoice_status', [
            'draft' => 'draft',
            'cancelled' => 'cancelled',
            'paid' => 'paid',
        ], 'open');

        // Entwurf und Storno sind nicht geldwirksam; alles andere ist Erlös.
        $sign = "CASE WHEN orgamax_invoices.invoice_status IN ('draft', 'cancelled') THEN 0 ELSE 1 END";

        return DB::table('orgamax_invoices')
            ->selectRaw($this->projection([
                "'orgamax_invoice' AS source_type",
                'orgamax_invoices.id AS source_id',
                'orgamax_invoices.id AS link_id',
                "'" . DocumentOrigin::OrgaMax->value . "' AS origin",
                "'" . DocumentDirection::Outgoing->value . "' AS direction",
                "$kind AS kind",
                "$sign AS sign",
                "COALESCE(orgamax_invoices.invoice_number, '') AS number",
                'orgamax_invoices.invoice_date AS doc_date',
                'orgamax_invoices.due_on AS due_on',
                "$state AS state",
                '0 AS is_archived',
                "CASE WHEN orgamax_invoices.customer_id IS NOT NULL THEN 'customer' ELSE NULL END AS contact_type",
                'orgamax_invoices.customer_id AS contact_id',
                "COALESCE(
                    (SELECT customers.name FROM customers WHERE customers.id = orgamax_invoices.customer_id),
                    orgamax_invoices.customer_name,
                    ''
                ) AS contact_name",
                '0 AS dunning_level',
                'COALESCE(orgamax_invoices.total_gross, 0) AS amount_gross',
                "CASE WHEN $state = 'open'
                    THEN COALESCE(orgamax_invoices.outstanding_amount, orgamax_invoices.total_gross, 0) ELSE 0 END AS open_amount",
                'orgamax_invoices.currency AS currency',
            ]))
            ->where('orgamax_invoices.organization_id', $f->organizationId)
            ->where('orgamax_invoices.invoice_type', '!=', 'recurringInvoiceTemplate')
            ->whereNotNull('orgamax_invoices.invoice_date')
            ->whereBetween('orgamax_invoices.invoice_date', [$f->from->toDateString(), $f->to->toDateString()]);
    }

    /**
     * Eingangsrechnungen aus dem Prüfbereich. Übertragene Belege werden
     * ausgelassen — dort führt der Buchhaltungsbeleg (Dublettenregel 2).
     */
    private function incomingEInvoices(): ?Builder {
        $f = $this->filters;
        if (! $f->allows('incoming_einvoice') || ! $f->wantsOrigin(DocumentOrigin::Local) || ! $f->wantsFixed(DocumentDirection::Incoming, DocumentKind::Invoice)) {
            return null;
        }

        $state = $this->caseMap('incoming_einvoices.status', [
            IncomingEInvoice::STATUS_REJECTED => 'cancelled',
            IncomingEInvoice::STATUS_PAYMENT_RELEASED => 'paid',
        ], 'open');

        $sign = "CASE WHEN incoming_einvoices.status = '" . IncomingEInvoice::STATUS_REJECTED . "' THEN 0 ELSE 1 END";

        return DB::table('incoming_einvoices')
            ->selectRaw($this->projection([
                "'incoming_einvoice' AS source_type",
                'incoming_einvoices.id AS source_id',
                'incoming_einvoices.document_id AS link_id',
                "'" . DocumentOrigin::Local->value . "' AS origin",
                "'" . DocumentDirection::Incoming->value . "' AS direction",
                "'" . DocumentKind::Invoice->value . "' AS kind",
                "$sign AS sign",
                "COALESCE(incoming_einvoices.invoice_number, '') AS number",
                'COALESCE(incoming_einvoices.issue_date, DATE(incoming_einvoices.received_at)) AS doc_date',
                'incoming_einvoices.due_date AS due_on',
                "$state AS state",
                '0 AS is_archived',
                'NULL AS contact_type',
                'NULL AS contact_id',
                'incoming_einvoices.seller_name AS contact_name',
                '0 AS dunning_level',
                'COALESCE(incoming_einvoices.amount_gross, 0) AS amount_gross',
                "CASE WHEN $state = 'open' THEN COALESCE(incoming_einvoices.amount_gross, 0) ELSE 0 END AS open_amount",
                "COALESCE(incoming_einvoices.currency, '" . $this->defaultCurrency() . "') AS currency",
            ]))
            ->where('incoming_einvoices.organization_id', $f->organizationId)
            ->whereNull('incoming_einvoices.transferred_at')
            ->whereBetween(
                DB::raw('COALESCE(incoming_einvoices.issue_date, DATE(incoming_einvoices.received_at))'),
                [$f->from->toDateString(), $f->to->toDateString()]
            );
    }

    /**
     * Auslagen. Sichtbarkeit folgt der ExpensePolicy: eigene immer, alle nur
     * mit Adminrecht — und dann steuert derselbe Umfang auch das
     * Kennzahlenband.
     *
     * Verknüpfte Auslagen (MVP-551) sind nicht mehr geldwirksam: dort führt
     * der zugeordnete Buchhaltungsbeleg.
     */
    private function expenses(): ?Builder {
        $f = $this->filters;
        if (! $f->allows('expense') || ! $f->wantsOrigin(DocumentOrigin::Local) || ! $f->wantsFixed(DocumentDirection::Incoming, DocumentKind::Expense)) {
            return null;
        }

        $state = $this->caseMap('expenses.status', [
            ExpenseStatus::Draft->value => 'draft',
            ExpenseStatus::Rejected->value => 'cancelled',
            ExpenseStatus::Cancelled->value => 'cancelled',
            ExpenseStatus::Reimbursed->value => 'paid',
            ExpenseStatus::Invoiced->value => 'paid',
        ], 'open');

        $sign = "CASE
            WHEN expenses.status IN ('" . ExpenseStatus::Rejected->value . "', '" . ExpenseStatus::Cancelled->value . "', '" . ExpenseStatus::Draft->value . "') THEN 0
            WHEN feed_link.id IS NOT NULL THEN 0
            ELSE 1 END";

        $query = DB::table('expenses')
            // Bestätigte Zuordnung zum Buchhaltungsbeleg (MVP-551) als JOIN,
            // nicht als SQL-Literal: der Klassenname enthält Backslashes, die
            // MariaDB in Stringliteralen als Escapes liest.
            ->leftJoin('external_references as feed_link', function ($join): void {
                $join->on('feed_link.referenceable_id', '=', 'expenses.id')
                    ->where('feed_link.referenceable_type', Expense::class)
                    ->where('feed_link.plugin_id', LexofficePlugin::ID)
                    ->where('feed_link.external_type', LexofficePlugin::EXT_TYPE_VOUCHER);
            })
            ->selectRaw($this->projection([
                "'expense' AS source_type",
                'expenses.id AS source_id',
                'expenses.id AS link_id',
                "'" . DocumentOrigin::Local->value . "' AS origin",
                "'" . DocumentDirection::Incoming->value . "' AS direction",
                "'" . DocumentKind::Expense->value . "' AS kind",
                "$sign AS sign",
                "COALESCE(expenses.reimbursement_reference, '') AS number",
                'expenses.date AS doc_date',
                'NULL AS due_on',
                "$state AS state",
                '0 AS is_archived',
                'NULL AS contact_type',
                'NULL AS contact_id',
                "COALESCE(expenses.vendor, '') AS contact_name",
                '0 AS dunning_level',
                'COALESCE(expenses.amount_gross, 0) AS amount_gross',
                '0 AS open_amount',
                'expenses.currency AS currency',
            ]))
            ->where('expenses.organization_id', $f->organizationId)
            ->whereBetween('expenses.date', [$f->from->toDateString(), $f->to->toDateString()]);

        if (! $f->allExpenses) {
            $query->where('expenses.user_id', $f->userId);
        }

        // Arbeitsliste „noch nicht verbucht": macht aus der Dublettengefahr
        // eine abarbeitbare Aufgabe statt einer stillen Unschärfe.
        if ($f->onlyUnlinkedExpenses) {
            $query->whereNull('feed_link.id');
        }

        return $query;
    }

    /** Spaltengleiche Leermenge, falls kein Sub-Select zum Filter passt. */
    private function emptySet(): Builder {
        return Invoice::query()->toBase()
            ->selectRaw($this->projection([
                "'invoice' AS source_type",
                'invoices.id AS source_id',
                'invoices.id AS link_id',
                "'' AS origin",
                "'' AS direction",
                "'' AS kind",
                '0 AS sign',
                "'' AS number",
                'invoices.issued_on AS doc_date',
                'invoices.due_on AS due_on',
                "'' AS state",
                '0 AS is_archived',
                'NULL AS contact_type',
                'NULL AS contact_id',
                "'' AS contact_name",
                '0 AS dunning_level',
                '0 AS amount_gross',
                '0 AS open_amount',
                "'" . $this->defaultCurrency() . "' AS currency",
            ]))
            ->whereRaw('1 = 0');
    }

    /**
     * Spaltenliste einer Projektion. Jedes Fragment stammt aus Enums,
     * Modellkonstanten oder festen Spaltennamen — nie aus Eingaben. Nach dem
     * implode kann PHPStan die literal-string-Eigenschaft nicht mehr beweisen
     * (Muster wie in Services/Integration/Match).
     *
     * @param  list<string>  $columns
     * @return literal-string
     */
    private function projection(array $columns): string {
        // @phpstan-ignore return.type
        return implode(', ', $columns);
    }

    /**
     * Baut ein `CASE <column> WHEN … END` aus einer Wertetabelle. Alle
     * Schlüssel und Werte stammen aus Enums/Modellkonstanten, nie aus
     * Nutzereingaben.
     *
     * @param  array<string, string>  $map
     */
    private function caseMap(string $column, array $map, string $default, bool $quoted = true): string {
        $sql = "CASE $column";
        foreach ($map as $when => $then) {
            $value = $quoted ? "'" . $then . "'" : $then;
            $sql .= " WHEN '" . $when . "' THEN " . $value;
        }
        $sql .= ' ELSE ' . ($quoted ? "'" . $default . "'" : $default) . ' END';

        return $sql;
    }

    private function defaultCurrency(): string {
        $code = (string) config('invoicing.default_currency', 'EUR');

        return preg_match('/^[A-Z]{3}$/', $code) === 1 ? $code : 'EUR';
    }
}
