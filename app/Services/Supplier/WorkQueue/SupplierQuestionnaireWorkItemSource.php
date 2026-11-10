<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierQuestionnaireWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Supplier\WorkQueue;

use App\Enums\Supplier\SupplierQuestionnaireStatus;
use App\Enums\User\Permission;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Platform\{Organization, User};
use App\Models\Supplier\SupplierQuestionnaireRequest;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Support\CarbonFmt;
use Illuminate\Support\Facades\Gate;

/** Beantwortete Lieferanten-Selbstauskünfte zur Prüfung (MVP-937); prüfen darf, wer Lieferanten bearbeitet. */
final class SupplierQuestionnaireWorkItemSource implements WorkItemSource {
    private const string ROUTE = 'supplier-questionnaires.index';

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'supplier_questionnaire';
    }

    public function label(): string {
        return (string) __('work-queue.source.supplier_questionnaire');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Approval;
    }

    public function available(User $viewer): bool {
        $gate = Gate::forUser($viewer);

        return $this->features->routeEnabled(self::ROUTE)
            && $gate->allows(Permission::SupplierViewAny->value)
            && $gate->allows(Permission::SupplierUpdate->value);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $submitted = SupplierQuestionnaireRequest::query()
            ->where('organization_id', $organization->id)
            ->where('status', SupplierQuestionnaireStatus::Submitted->value)
            ->with(['supplier:id,name', 'questionnaire:id,name'])
            ->orderBy('submitted_at')
            ->get();

        foreach ($submitted as $request) {
            yield new WorkItem(
                source: $this->key(),
                key: $request->getMorphClass() . ':' . $request->id,
                title: (string) __('work-queue.item.supplier_questionnaire', ['supplier' => $request->supplier->name ?? '—']),
                url: route(self::ROUTE),
                detail: implode(' · ', array_filter([
                    $request->questionnaire->name ?? null,
                    $request->submitted_at !== null ? CarbonFmt::fdate($request->submitted_at) : null,
                ])) ?: null,
            );
        }
    }
}
