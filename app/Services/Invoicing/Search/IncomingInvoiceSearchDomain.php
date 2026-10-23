<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Search;

use App\Models\Document\Document;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\User;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use App\Support\CarbonFmt;
use Illuminate\Support\Facades\Gate;

/** Eingangsrechnungen nach Nummer, Absender und Empfänger — sichtbar wie in der Arbeitsliste (Original im DMS). */
final class IncomingInvoiceSearchDomain implements SearchDomain {
    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'incoming_invoices';
    }

    public function label(): string {
        return (string) __('search.domain.incoming_invoices');
    }

    public function icon(): string {
        return 'move_to_inbox';
    }

    public function order(): int {
        return 36;
    }

    public function available(User $user): bool {
        return $this->features->routeEnabled('finance.incoming-invoices.show') && Gate::forUser($user)->allows('viewAny', Document::class);
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        if ($filters->person !== null) {
            return [];
        }
        $query = IncomingEInvoice::query()
            ->visibleTo($user)
            ->where('organization_id', $organizationId)
            ->where(fn ($q) => $q->whereLikeEscaped('invoice_number', $term)
                ->orWhereLikeEscaped('seller_name', $term)
                ->orWhereLikeEscaped('buyer_name', $term))
            ->when($filters->customer !== null, fn ($q) => $q->where('customer_id', $filters->customer))
            ->with('document');
        $filters->range($query, 'received_at');

        return array_values($query->orderByDesc('received_at')->limit($limit)->get()
            ->filter(fn (IncomingEInvoice $i): bool => $i->document !== null)
            ->map(fn (IncomingEInvoice $i) => [
                'id' => $i->id,
                'title' => (string) ($i->invoice_number ?: $i->seller_name ?: '—'),
                'subtitle' => implode(' · ', array_filter([
                    $i->invoice_number ? $i->seller_name : null,
                    CarbonFmt::fdate($i->received_at),
                ])) ?: null,
                'url' => route('finance.incoming-invoices.show', $i->document),
            ])
            ->all());
    }
}
