<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComputeTourProposal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Routing;

use App\Jobs\Contracts\ModuleJob;
use App\Models\Diary\TourProposal;
use App\Models\Platform\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Services\Routing\TourProposalService;
use App\Support\OrganizationContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldBeUnique, ShouldQueue};
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

/** Rechnet einen Tourvorschlag (MVP-1176); je Vorschlag genau ein Lauf. */
final class ComputeTourProposal implements ModuleJob, ShouldBeUnique, ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public function __construct(public readonly int $proposalId) {}

    public function uniqueId(): string {
        return (string) $this->proposalId;
    }

    public function organizationId(): ?int {
        $id = TourProposal::query()->withoutGlobalScopes()->whereKey($this->proposalId)->value('organization_id');

        return $id !== null ? (int) $id : null;
    }

    public function handle(TourProposalService $service): void {
        $proposal = TourProposal::query()->withoutGlobalScope(OrganizationScope::class)->find($this->proposalId);
        if (! $proposal instanceof TourProposal) {
            return;
        }
        $organization = Organization::query()->find($proposal->organization_id);
        $work = static fn() => $service->compute($proposal);
        $organization instanceof Organization ? OrganizationContext::run($organization, $work) : $work();
    }
}
