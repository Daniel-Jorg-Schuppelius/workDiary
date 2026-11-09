<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucherPartyDocumentSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Accounting\Vouchers;

use App\Enums\Billing\{DocumentDirection, DocumentKind, DocumentOrigin};
use App\Enums\Finance\AccountingVoucherState;
use App\Models\Customer\Customer;
use App\Models\Finance\AccountingVoucher;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Billing\Contracts\PartyDocumentSource;
use App\Services\Billing\Dto\{PartyDocument, PartyDocumentList};
use App\Services\Billing\Feed\DocumentFeedSourceRegistry;
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Belegliste der Kunden- und Lieferantenakte aus dem allgemeinen Belegspiegel
 * `accounting_vouchers` (MVP-1496): eine Quelle je Programm, aktiv, solange
 * sein Belegabruf für die Organisation eingerichtet ist. Ohne Serienvorlagen
 * und im Fremdsystem gelöschte Belege; Original und PDF über die
 * {@see \App\Services\Billing\Feed\MirroredVoucherLinks} des Programms.
 */
final class AccountingVoucherPartyDocumentSource implements PartyDocumentSource {
    private const int LIMIT = 500;

    public function __construct(private readonly DocumentOrigin $origin) {}

    public function key(): string {
        return AccountingVoucherRevenueSource::KEY . ':' . $this->origin->value;
    }

    public function documentsFor(Customer|Supplier $party, CarbonInterface $from, CarbonInterface $to): ?PartyDocumentList {
        $organizationId = (int) $party->organization_id;
        if (app(VoucherPullerRegistry::class)->find($this->origin->value)?->isConfigured($organizationId) !== true) {
            return null;
        }

        $vouchers = AccountingVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', $this->origin->value)
            ->where($party instanceof Customer ? 'customer_id' : 'supplier_id', $party->getKey())
            ->where('archived', false)
            ->whereNull('source_deleted_at')
            ->where(static fn (Builder $q) => $q->whereNull('document_kind')->orWhere('document_kind', '!=', DocumentKind::RecurringTemplate->value))
            ->whereBetween('voucher_date', DateRange::days($from, $to))
            ->orderByDesc('voucher_date')
            ->limit(self::LIMIT)
            ->get(['id', 'organization_id', 'plugin_id', 'external_id', 'voucher_type', 'voucher_status', 'voucher_state', 'direction', 'document_kind', 'voucher_number', 'voucher_date', 'due_date', 'total_amount', 'currency']);

        $links = app(DocumentFeedSourceRegistry::class)->voucherLinksFor($this->origin->value);
        $user = auth()->user();
        $today = Carbon::today();
        $documents = [];
        foreach ($vouchers as $voucher) {
            $state = AccountingVoucherRevenueSource::state($voucher);
            $overdue = $state === AccountingVoucherState::Open && $voucher->due_date !== null && $voucher->due_date->lt($today);
            $documents[] = new PartyDocument(
                type: self::type($voucher),
                number: $voucher->voucher_number,
                date: $voucher->voucher_date,
                status: $overdue ? 'overdue' : $state->value,
                // Als Betrag wie im Belegfluss; die Art nennt Gutschrift und Storno.
                amount: abs((float) ($voucher->total_amount ?? 0)),
                currency: (string) ($voucher->currency ?: 'EUR'),
                actions: $links !== null && $user instanceof User ? $links->actionsFor($voucher, $user) : [],
            );
        }

        // Programme mit Stammdatenimport (orgaMAX) verknüpfen auch unter `customer` bzw. `supplier`.
        $linked = ExternalReference::query()
            ->forPlugin($organizationId, $this->origin->value)
            ->whereIn('external_type', [ExternalReference::TYPE_CONTACT, $party instanceof Customer ? 'customer' : 'supplier'])
            ->forReferenceable($party)
            ->exists();

        return new PartyDocumentList(
            source: $this->origin->label(),
            linked: $linked,
            documents: $documents,
            unlinkedHint: $linked ? null : (string) __('billing.party_documents.mirror_unlinked', ['program' => $this->origin->label()]),
        );
    }

    /** Belegart als `values.<typ>` der Belegliste (Richtung × Vorgangsart). */
    private static function type(AccountingVoucher $voucher): string {
        $direction = DocumentDirection::tryFrom((string) $voucher->direction)
            ?? match ($voucher->voucher_type) {
                'C' => DocumentDirection::Incoming,
                'D' => DocumentDirection::Outgoing,
                default => DocumentDirection::Neutral,
            };
        $incoming = $direction === DocumentDirection::Incoming;

        return match (DocumentKind::tryFrom((string) $voucher->document_kind) ?? DocumentKind::Other) {
            DocumentKind::Invoice => $incoming ? 'purchaseinvoice' : 'salesinvoice',
            DocumentKind::CreditNote => $incoming ? 'purchasecreditnote' : 'salescreditnote',
            DocumentKind::DownPayment => 'downpaymentinvoice',
            DocumentKind::DownPaymentDeduction => 'downpaymentdeduction',
            DocumentKind::Cancellation => 'cancellationinvoice',
            DocumentKind::Reminder => 'dunningnotice',
            default => $incoming ? 'purchasevoucher' : 'salesvoucher',
        };
    }
}
