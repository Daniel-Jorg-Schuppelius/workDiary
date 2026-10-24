<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FireflyTransactionSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Firefly\Search;

use App\Models\Platform\User;
use App\Plugins\Firefly\FireflyPlugin;
use App\Plugins\Firefly\Models\FireflyTransaction;
use App\Plugins\PluginManager;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use App\Support\CarbonFmt;
use CommonToolkit\Helper\Data\StringHelper;

/** Firefly-Bewegungen in der globalen Suche (Feature 180/181): Verwendungszweck, Gegenpartei, Belegnummer; nur für Admins. */
final class FireflyTransactionSearchDomain implements SearchDomain {
    public function __construct(private readonly PluginManager $plugins) {}

    public function key(): string {
        return 'firefly_transactions';
    }

    public function label(): string {
        return (string) __('firefly::firefly.search.domain');
    }

    public function icon(): string {
        return 'account_balance';
    }

    public function order(): int {
        return 39;
    }

    public function available(User $user): bool {
        return $user->isAdmin() && $this->plugins->enabled()->has(FireflyPlugin::ID);
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        if ($filters->person !== null || $filters->customer !== null) {
            return [];
        }
        $query = FireflyTransaction::query()->where('organization_id', $organizationId)->where(fn ($q) => FireflyTransaction::whereMatching($q, $term));
        $filters->range($query, 'booked_on', dateColumn: true);

        return array_values(array_map(static fn (FireflyTransaction $t): array => [
            'id' => $t->id,
            'title' => $t->booked_amount->format() . ' · ' . ($t->counterparty_name ?? $t->kind->label()),
            'subtitle' => implode(' · ', array_filter([CarbonFmt::fdate($t->booked_on), $t->description !== null ? StringHelper::truncate($t->description, 80) : null])) ?: null,
            'url' => route('admin.firefly.index', ['q' => $term]),
        ], $query->orderByDesc('booked_on')->orderByDesc('id')->limit($limit)->get()->all()));
    }
}
