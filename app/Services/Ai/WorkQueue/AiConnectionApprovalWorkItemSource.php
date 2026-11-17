<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiConnectionApprovalWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\WorkQueue;

use App\Enums\Ai\{AiApprovalState, AiConnectionStatus};
use App\Enums\User\Permission;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Ai\AiProviderConnection;
use App\Models\Platform\{Organization, User};
use App\Services\Ai\AiConnectionPrivacy;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Datenschutzfreigaben der KI-Verbindungen im Arbeitsvorrat (Feature 025,
 * MVP-1672): nicht freigegebene Verbindungen als offene Aufgabe — auch, wenn
 * die Organisation die Freigabe noch nicht verlangt (Bestand nach dem
 * Einspielen) —, ablaufende mit Fälligkeit am Ablauftag, abgelaufene als
 * überfällig. Bewusst gesperrte Verbindungen brauchen keine Freigabe.
 */
final class AiConnectionApprovalWorkItemSource implements WorkItemSource {
    private const string ROUTE = 'admin.ai.index';

    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly AiConnectionPrivacy $privacy,
    ) {}

    public function key(): string {
        return 'ai_connection_approval';
    }

    public function label(): string {
        return (string) __('work-queue.source.ai_connection_approval');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Approval;
    }

    public function available(User $viewer): bool {
        return $this->features->routeEnabled(self::ROUTE) && Gate::forUser($viewer)->allows(Permission::AiApprove->value);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $connections = AiProviderConnection::query()
            ->where('organization_id', $organization->id)
            ->where('status', '!=', AiConnectionStatus::Blocked->value)
            ->orderBy('name')
            ->get();

        $now = CarbonImmutable::now();
        $required = $this->privacy->requiresApproval($organization);

        foreach ($connections as $connection) {
            $state = $this->privacy->state($connection, $organization, $now);
            if ($state === AiApprovalState::Approved) {
                continue;
            }
            $expiresAt = $this->privacy->expiresAt($connection, $organization);

            yield new WorkItem(
                source: $this->key(),
                key: $connection->getMorphClass() . ':' . $connection->id . ':approval',
                title: (string) __($state === AiApprovalState::Missing ? 'work-queue.item.ai_connection_approval.approve' : 'work-queue.item.ai_connection_approval.renew', ['name' => $connection->name]),
                url: route(self::ROUTE),
                detail: match (true) {
                    $state !== AiApprovalState::Missing && $expiresAt !== null => (string) __('work-queue.detail.ai_approval_expires', ['date' => $expiresAt->isoFormat('L')]),
                    $required => (string) __('work-queue.detail.ai_approval_blocking'),
                    default => (string) __('work-queue.detail.ai_approval_optional'),
                },
                dueAt: $state === AiApprovalState::Missing ? null : $expiresAt,
            );
        }
    }
}
