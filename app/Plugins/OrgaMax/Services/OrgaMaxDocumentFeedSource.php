<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxDocumentFeedSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Services;

use App\Enums\Billing\{DocumentDirection, DocumentKind, DocumentOrigin};
use App\Models\Platform\User;
use App\Plugins\OrgaMax\Enums\OrgaMaxInvoiceStatus;
use App\Plugins\OrgaMax\Models\OrgaMaxInvoice;
use App\Plugins\OrgaMax\{OrgaMaxPlugin, OrgaMaxServiceProvider};
use App\Services\Billing\DocumentFeedFilters;
use App\Services\Billing\Feed\{DocumentFeedSource, FeedProjection, PresentsFeedRows, SuppressesCoreInvoices};
use App\Support\Query\DateRange;
use App\Support\Sqid;
use App\Support\Ui\UiAction;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\{DB, Route};

/**
 * Gespiegelte orgaMAX-Rechnungen im Belegfluss (MVP-670). Gleiche Rolle wie
 * die Lexoffice-Quelle, nur mit der Belegsemantik des anderen Systems:
 * orgaMAX führt ausschließlich Ausgangsbelege, Wiederholungs-*Vorlagen*
 * sind kein Beleg und bleiben draußen. Registriert über den
 * {@see OrgaMaxServiceProvider} — der Kern kennt `orgamax_invoices` nicht.
 */
class OrgaMaxDocumentFeedSource implements DocumentFeedSource, PresentsFeedRows, SuppressesCoreInvoices {
    public function key(): string {
        return OrgaMaxPlugin::ID;
    }

    public function builder(DocumentFeedFilters $f): ?Builder {
        if (! $f->allows('voucher') || ! $f->wantsOrigin(DocumentOrigin::OrgaMax) || ! $f->wantsFixed(DocumentDirection::Outgoing)) {
            return null;
        }

        // Abschlagsrechnung ist eine Anzahlung, Schluss- und
        // Wiederholungsrechnung sind gewöhnliche Rechnungen.
        $kind = FeedProjection::caseMap('orgamax_invoices.invoice_type', [
            'depositInvoice' => DocumentKind::DownPayment->value,
        ], DocumentKind::Invoice->value);

        // Gesperrt, teilbezahlt und unbekannt gelten als offen (Rest fällig).
        $state = FeedProjection::caseMap('orgamax_invoices.invoice_status', [
            OrgaMaxInvoiceStatus::Draft->value => 'draft',
            OrgaMaxInvoiceStatus::Cancelled->value => 'cancelled',
            OrgaMaxInvoiceStatus::Paid->value => 'paid',
        ], 'open');

        // Entwurf und Storno sind nicht geldwirksam; alles andere ist Erlös.
        $sign = sprintf(
            "CASE WHEN orgamax_invoices.invoice_status IN ('%s', '%s') THEN 0 ELSE 1 END",
            OrgaMaxInvoiceStatus::Draft->value,
            OrgaMaxInvoiceStatus::Cancelled->value,
        );

        return DB::table('orgamax_invoices')
            ->selectRaw(FeedProjection::columns([
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
            ->whereBetween('orgamax_invoices.invoice_date', DateRange::days($f->from, $f->to));
    }

    /** Dieselbe Dublettenregel wie beim Lexoffice-Spiegel (MVP-670): extern führt. */
    public function suppressCoreInvoices(Builder $invoices): void {
        $invoices->whereNotExists(function (Builder $sub): void {
            $sub->select(DB::raw(1))
                ->from('orgamax_invoices')
                ->whereColumn('orgamax_invoices.organization_id', 'invoices.organization_id')
                ->where(function (Builder $q): void {
                    $q->whereColumn('orgamax_invoices.invoice_number', 'invoices.number')
                        ->orWhereColumn('orgamax_invoices.invoice_number', 'invoices.external_number');
                });
        });
    }

    public function presents(\stdClass $row): bool {
        return $row->source_type === 'orgamax_invoice';
    }

    public function rowLink(\stdClass $row, User $user): ?UiAction {
        // Keine eigene Detailseite: das PDF liegt hinter der Admin-Route (MVP-670).
        if (! $user->isAdmin() || ! Route::has('admin.orgamax.invoices.pdf')) {
            return null;
        }

        return new UiAction('picture_as_pdf', (string) __('PDF'), route('admin.orgamax.invoices.mirror-pdf', Sqid::encode(OrgaMaxInvoice::class, (int) $row->link_id)), modal: true);
    }

    public function rowActions(\stdClass $row, User $user, bool $overdue): array {
        return [];
    }
}
