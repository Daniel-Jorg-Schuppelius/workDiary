<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucherSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Search;

use App\Enums\Billing\DocumentOrigin;
use App\Enums\User\Permission;
use App\Models\Platform\User;
use App\Services\Billing\{DocumentFeedFilters, DocumentFeedQuery};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use App\Support\CarbonFmt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Belege aus den Buchhaltungsprogrammen (Feature 181) — dieselben Zeilen wie
 * der Belegfluss mit der Quelle `voucher`, also jedes angeschlossene Programm.
 * Ein Treffer öffnet den Belegfluss auf Nummer, Herkunft und Belegdatum.
 */
final class AccountingVoucherSearchDomain implements SearchDomain {
    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'accounting_vouchers';
    }

    public function label(): string {
        return (string) __('search.domain.accounting_vouchers');
    }

    public function icon(): string {
        return 'receipt_long';
    }

    public function order(): int {
        return 37;
    }

    public function available(User $user): bool {
        return $this->features->routeEnabled('billing.feed') && Gate::forUser($user)->allows(Permission::VoucherViewAny->value);
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        if ($filters->person !== null) {
            return [];
        }
        $feed = new DocumentFeedQuery(new DocumentFeedFilters(
            organizationId: $organizationId,
            userId: (int) $user->id,
            from: $filters->from !== null ? CarbonImmutable::parse($filters->from)->startOfDay() : CarbonImmutable::parse('1900-01-01'),
            to: $filters->to !== null ? CarbonImmutable::parse($filters->to)->endOfDay() : CarbonImmutable::parse('2999-12-31'),
            customerId: $filters->customer,
            search: $term,
            sources: ['voucher' => true],
        ));

        return array_map(function (\stdClass $row): array {
            $number = (string) ($row->number ?? '');
            $contact = (string) ($row->contact_name ?? '');
            $date = (string) $row->doc_date;

            return [
                'id' => $row->origin . ':' . $row->source_id,
                'title' => $number !== '' ? $number : ($contact !== '' ? $contact : '—'),
                'subtitle' => implode(' · ', array_filter([
                    $number !== '' ? $contact : null,
                    DocumentOrigin::tryFrom((string) $row->origin)?->label(),
                    $date !== '' ? CarbonFmt::fdate(CarbonImmutable::parse($date)) : null,
                ])) ?: null,
                'url' => route('billing.feed', array_filter([
                    'q' => $number !== '' ? $number : $contact,
                    'origin' => (string) $row->origin,
                    'from' => $date !== '' ? substr($date, 0, 10) : null,
                    'to' => $date !== '' ? substr($date, 0, 10) : null,
                ])),
            ];
        }, $feed->latest($limit));
    }
}
