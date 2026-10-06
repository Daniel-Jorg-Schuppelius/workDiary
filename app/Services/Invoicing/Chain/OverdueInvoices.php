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
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{Organization, User};
use App\Services\Billing\Contracts\DocumentChainSource;
use App\Services\Billing\Dto\DocumentChainItem;
use App\Support\CarbonFmt;
use Illuminate\Database\Eloquent\Builder;

/** Belegkette (MVP-1057): ausgestellte Rechnungen nach Fälligkeit, nicht oder nur teilweise bezahlt. */
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
        return $this->query($organization)->count();
    }

    public function items(Organization $organization, int $limit): array {
        return array_values($this->query($organization)->with('customer:id,name,company')->orderBy('due_on')->limit($limit)->get()
            ->map(fn (Invoice $invoice): DocumentChainItem => new DocumentChainItem(
                title: $invoice->number . ' · ' . ($invoice->customer->displayLabel()),
                detail: (string) __('invoicing.chain.due_on', ['date' => ($invoice->due_on !== null ? CarbonFmt::fdate($invoice->due_on) : '—')]),
                url: route('invoices.show', $invoice),
                amount: $invoice->total,
                date: $invoice->due_on,
            ))->all());
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
