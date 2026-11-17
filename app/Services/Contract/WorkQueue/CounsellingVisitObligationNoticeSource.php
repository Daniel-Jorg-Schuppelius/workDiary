<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CounsellingVisitObligationNoticeSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Contract\WorkQueue;

use App\Enums\Contract\ContractObligationStatus;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Contract\{Contract, ContractObligation};
use App\Models\Platform\{Organization, User};
use App\Services\Customer\Deadlines\CustomerDeadlineRuleSets;
use App\Services\Customer\Deadlines\RuleSets\HomeCareDeadlineRuleSet;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Hinweis zur Berichtigung des Pflegeprofils (Feature 197, MVP-1673): Die
 * frühere Pflegevertrags-Vorlage legte den Beratungsbesuch nach § 37 Abs. 3
 * SGB XI als Vertragspflicht alle drei Monate für jeden Kunden an. Seit der
 * Regelsatz ambulante Pflege die Frist aus Pflegegrad und Pflegegeld rechnet,
 * sind diese Pflichten doppelt und für die meisten Kunden falsch. Der Bestand
 * wird nie still geändert — der Arbeitsvorrat zeigt der Vertragsverwaltung
 * einen gebündelten Hinweis, sie zu schließen, solange offene Pflichten dieser
 * Art bestehen und der Regelsatz eingeschaltet ist.
 */
final class CounsellingVisitObligationNoticeSource implements WorkItemSource {
    /** Titel der Pflicht aus der Vorlage bis Profilversion 7. */
    public const string LEGACY_TITLE = 'Beratungsbesuch nach § 37 Abs. 3 SGB XI';

    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly CustomerDeadlineRuleSets $ruleSets,
    ) {}

    public function key(): string {
        return 'counselling_visit_obligations';
    }

    public function label(): string {
        return (string) __('work-queue.source.counselling_visit_obligations');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Task;
    }

    public function available(User $viewer): bool {
        return $this->features->isEnabled('module.contracts') && Gate::forUser($viewer)->allows('create', Contract::class);
    }

    public function items(User $viewer, Organization $organization): iterable {
        if (! in_array(HomeCareDeadlineRuleSet::KEY, $this->ruleSets->enabledKeys($organization), true)) {
            return;
        }
        $count = $this->legacy((int) $organization->id)->count();
        if ($count === 0) {
            return;
        }

        yield new WorkItem(
            source: $this->key(),
            key: $this->key() . ':' . $organization->id,
            title: trans_choice('customer_deadline.care_notice.title', $count, ['count' => $count]),
            url: route('contracts.index'),
            detail: (string) __('customer_deadline.care_notice.detail'),
            count: $count,
        );
    }

    /** @return Builder<ContractObligation> offene, vierteljährlich wiederkehrende Pflichten aus der alten Vorlage */
    public function legacy(int $organizationId): Builder {
        return ContractObligation::query()
            ->where('organization_id', $organizationId)
            ->where('title', self::LEGACY_TITLE)
            ->where('recurring', true)
            ->where('recurrence_months', 3)
            ->where('status', ContractObligationStatus::Open->value);
    }
}
