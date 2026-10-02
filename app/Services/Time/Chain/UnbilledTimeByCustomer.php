<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UnbilledTimeByCustomer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Time\Chain;

use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Time\TimeEntry;
use App\Services\Billing\Contracts\DocumentChainSource;
use App\Services\Billing\Dto\DocumentChainItem;
use App\Support\{Formats, Sqid};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/** Belegkette (MVP-1057): abrechenbare, noch nicht abgerechnete Zeiten je Kunde (Liste „Offene Zeiten“). */
final class UnbilledTimeByCustomer implements DocumentChainSource {
    public function key(): string {
        return 'unbilled_time';
    }

    public function label(): string {
        return (string) __('invoicing.chain.unbilled_time');
    }

    public function icon(): string {
        return 'schedule';
    }

    public function routeName(): string {
        return 'finance.open-times.index';
    }

    public function availableFor(User $user): bool {
        // Wie die Liste „Offene Zeiten“: Admin oder das Recht timeEntry.viewAny.
        return $user->isAdmin() || $user->can('timeEntry.viewAny');
    }

    public function count(Organization $organization): int {
        return (int) $this->query($organization)->distinct()->count('projects.customer_id');
    }

    public function items(Organization $organization, int $limit): array {
        return array_map(static fn (array $row): DocumentChainItem => new DocumentChainItem(
            title: $row['customer']?->displayLabel() ?? (string) __('invoicing.chain.no_customer'),
            detail: (string) __('invoicing.chain.unbilled_detail', [
                'entries' => $row['entries'],
                'duration' => Formats::duration($row['minutes'], 'clock'),
            ]),
            url: route('finance.open-times.index', $row['customer'] !== null ? ['customer' => Sqid::encode(Customer::class, (int) $row['customer']->id)] : []),
            date: $row['oldest'],
        ), $this->rows($organization, $limit));
    }

    /**
     * Offene Zeiten je Kunde, älteste zuerst — auch für das MCP-Werkzeug (MVP-1063).
     *
     * @return list<array{customer: ?Customer, entries: int, minutes: int, oldest: CarbonImmutable}>
     */
    public function rows(Organization $organization, int $limit): array {
        $rows = $this->query($organization)->toBase()
            ->selectRaw('projects.customer_id as customer_id, COUNT(*) as entries, SUM(time_entries.minutes) as minutes, MIN(time_entries.date) as oldest')
            ->groupBy('projects.customer_id')
            ->orderBy('oldest')
            ->limit($limit)
            ->get();
        $customers = Customer::query()->whereIn('id', $rows->pluck('customer_id')->filter()->all())->get()->keyBy('id');

        return array_values($rows->map(static fn (object $row): array => [
            'customer' => $customers->get((int) $row->customer_id),
            'entries' => (int) $row->entries,
            'minutes' => (int) $row->minutes,
            'oldest' => CarbonImmutable::parse((string) $row->oldest),
        ])->all());
    }

    /** @return Builder<TimeEntry> */
    private function query(Organization $organization): Builder {
        return TimeEntry::query()
            ->withoutLedgerManagedCustomers()
            ->where('time_entries.organization_id', $organization->id)
            ->where('time_entries.exported', false)
            ->where('time_entries.billable', true)
            ->join('projects', 'projects.id', '=', 'time_entries.project_id');
    }
}
