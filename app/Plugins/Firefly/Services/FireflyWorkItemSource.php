<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FireflyWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Firefly\Services;

use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Platform\{Organization, User};
use App\Plugins\Firefly\Enums\FireflyMatchStatus;
use App\Plugins\Firefly\FireflyPlugin;
use App\Plugins\Firefly\Models\FireflyTransaction;
use App\Plugins\PluginManager;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;

/**
 * Firefly-Bewegungen ohne eindeutige Zuordnung im Arbeitsvorrat (Feature 181,
 * MVP-1617): je Zuordnungsstand ein gebündelter Vorgang mit Link auf die
 * gefilterte Liste. Ohne Frist — die Bewegung ist schon gebucht.
 */
final class FireflyWorkItemSource implements WorkItemSource {
    public function __construct(private readonly PluginManager $plugins) {}

    public function key(): string {
        return 'firefly';
    }

    public function label(): string {
        return (string) __('firefly::firefly.work_queue.source');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Inbox;
    }

    public function available(User $viewer): bool {
        return $viewer->isAdmin() && $this->plugins->enabled()->has(FireflyPlugin::ID);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $counts = FireflyTransaction::query()
            ->where('organization_id', $organization->id)
            ->openForReview()
            ->selectRaw('match_status, COUNT(*) AS aggregate')
            ->groupBy('match_status')
            ->pluck('aggregate', 'match_status');

        foreach (FireflyMatchStatus::openValues() as $status) {
            $count = (int) ($counts[$status] ?? 0);
            if ($count === 0) {
                continue;
            }
            yield new WorkItem(
                source: $this->key(),
                key: 'firefly:' . $status,
                title: trans_choice('firefly::firefly.work_queue.' . $status, $count, ['count' => $count]),
                url: route('admin.firefly.index', ['match' => $status]),
                count: $count,
            );
        }
    }
}
