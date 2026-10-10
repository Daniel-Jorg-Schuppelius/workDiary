<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OverdueInvoices.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Chain;

use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{Organization, User};
use App\Services\Billing\Contracts\{DocumentChainSource, ExternalRevenue};
use App\Services\Billing\Dto\{DocumentChainItem, ExternalInvoice, ExternalInvoiceQuery};
use App\Support\CarbonFmt;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * Belegkette (MVP-1057): ausgestellte Rechnungen nach Fälligkeit, nicht oder
 * nur teilweise bezahlt — auch die des Buchhaltungsprogramms (MVP-1116), die
 * im Belegfluss geöffnet werden.
 */
final class OverdueInvoices implements DocumentChainSource {
    public function key(): string {
        return 'invoices_overdue';
    }

    public function label(): string {
        return (string) __('invoicing.chain.invoices_overdue');
    }

    public function icon(): string {
        return 'running_with_errors';
    }

    public function routeName(): string {
        return 'invoices.show';
    }

    public function availableFor(User $user): bool {
        return $user->can('viewAny', Invoice::class);
    }

    public function count(Organization $organization): int {
        return $this->query($organization)->count() + count($this->external($organization));
    }

    public function items(Organization $organization, int $limit): array {
        $items = $this->query($organization)->with('customer:id,name,company')->orderBy('due_on')->limit($limit)->get()
            ->map(fn (Invoice $invoice): DocumentChainItem => new DocumentChainItem(
                title: $invoice->number . ' · ' . ($invoice->customer->displayLabel()),
                detail: (string) __('invoicing.chain.due_on', ['date' => ($invoice->due_on !== null ? CarbonFmt::fdate($invoice->due_on) : '—')]),
                url: route('invoices.show', $invoice),
                amount: $invoice->total,
                date: $invoice->due_on,
            ))->all();

        $external = $this->external($organization);
        $customers = Customer::query()->whereIn('id', array_filter(array_map(static fn (ExternalInvoice $invoice): ?int => $invoice->customerId, $external)))
            ->get(['id', 'name', 'company'])->keyBy('id');
        foreach ($external as $invoice) {
            $party = $invoice->customerId !== null ? $customers->get($invoice->customerId)?->displayLabel() : null;
            $items[] = new DocumentChainItem(
                title: $invoice->number . ' · ' . ($party ?? __('reporting.unassigned_contact', ['name' => $invoice->contactName ?? '—'])),
                detail: (string) __('invoicing.chain.due_on', ['date' => ($invoice->dueOn !== null ? CarbonFmt::fdate($invoice->dueOn) : '—')]),
                url: route('billing.feed', ['q' => $invoice->number]),
                amount: Money::ofFloat($invoice->gross, CurrencyCode::tryFrom($invoice->currency) ?? CurrencyCode::Euro),
                date: $invoice->dueOn,
            );
        }
        usort($items, static fn (DocumentChainItem $a, DocumentChainItem $b): int => ($a->date?->getTimestamp() ?? PHP_INT_MAX) <=> ($b->date?->getTimestamp() ?? PHP_INT_MAX));

        return array_slice($items, 0, $limit);
    }

    /** @return list<ExternalInvoice> offene, überfällige Rechnungen und Abschläge des Buchhaltungsprogramms */
    private function external(Organization $organization): array {
        $today = now()->toDateString();

        return array_values(array_filter(
            app(ExternalRevenue::class)->invoices(new ExternalInvoiceQuery(withCreditNotes: false, openOnly: true, organizationId: (int) $organization->id)),
            static fn (ExternalInvoice $invoice): bool => $invoice->dueOn !== null && $invoice->dueOn->toDateString() < $today,
        ));
    }

    /** @return Builder<Invoice> */
    private function query(Organization $organization): Builder {
        return Invoice::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->whereNotIn('type', [Invoice::TYPE_PROFORMA, Invoice::TYPE_CANCELLATION, Invoice::TYPE_CREDIT_NOTE])
            ->whereNotNull('due_on')
            ->where('due_on', '<', now()->toDateString());
    }
}
