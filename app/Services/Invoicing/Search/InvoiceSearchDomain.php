<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Search;

use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use App\Support\CarbonFmt;
use Illuminate\Support\Facades\Gate;

/** Ausgangsrechnungen nach Nummer und Kundenname; Kundenfilter über den Rechnungskunden, kein Personenbezug. */
final class InvoiceSearchDomain implements SearchDomain {
    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'invoices';
    }

    public function label(): string {
        return (string) __('search.domain.invoices');
    }

    public function icon(): string {
        return 'request_quote';
    }

    public function order(): int {
        return 35;
    }

    public function available(User $user): bool {
        return $this->features->routeEnabled('invoices.show') && Gate::forUser($user)->allows('viewAny', Invoice::class);
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        if ($filters->person !== null) {
            return [];
        }
        $query = Invoice::query()
            ->where('organization_id', $organizationId)
            ->where(fn ($q) => $q->whereLikeEscaped('number', $term)
                ->orWhereHas('customer', fn ($c) => $c->whereLikeEscaped('name', $term)))
            ->when($filters->customer !== null, fn ($q) => $q->where('customer_id', $filters->customer))
            ->with('customer:id,name');
        $filters->range($query, 'issued_on', dateColumn: true);

        return array_values($query->orderByDesc('issued_on')->orderByDesc('id')->limit($limit)->get()
            ->map(fn (Invoice $i) => [
                'id' => $i->id,
                'title' => (string) ($i->number ?: __('search.domain.invoice_draft', ['id' => $i->id])),
                'subtitle' => implode(' · ', array_filter([
                    $i->customer->name,
                    $i->status->label(),
                    $i->issued_on !== null ? CarbonFmt::fdate($i->issued_on) : null,
                ])) ?: null,
                'url' => route('invoices.show', $i),
            ])
            ->all());
    }
}
