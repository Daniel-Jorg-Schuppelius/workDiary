<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NegativeStockInboxSubject.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory\Approvals;

use App\Enums\Inventory\NegativeStockRequestStatus;
use App\Models\Approval\Approval;
use App\Models\Inventory\NegativeStockRequest;
use App\Models\Platform\User;
use App\Services\Approval\Contracts\ApprovalInboxSubject;
use App\Services\Approval\Dto\ApprovalInboxEntry;
use App\Services\Inventory\NegativeStockApproval;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Model;

/** Entnahmen in den Negativbestand im Genehmigungs-Eingang (MVP-1626). */
final class NegativeStockInboxSubject implements ApprovalInboxSubject {
    public function __construct(private readonly NegativeStockApproval $approval) {}

    public function approvableClass(): string {
        return NegativeStockRequest::class;
    }

    public function eagerLoad(): array {
        return ['variant.article', 'warehouse', 'requester'];
    }

    public function awaitsDecision(Model $approvable): bool {
        return $approvable instanceof NegativeStockRequest && $approvable->status === NegativeStockRequestStatus::Pending;
    }

    public function requesterId(Model $approvable): ?int {
        return $approvable instanceof NegativeStockRequest && $approvable->requested_by !== null ? (int) $approvable->requested_by : null;
    }

    public function present(Model $approvable, User $viewer): ApprovalInboxEntry {
        if (! $approvable instanceof NegativeStockRequest) {
            return new ApprovalInboxEntry('—');
        }

        return new ApprovalInboxEntry(
            (string) __('inventory.negative.inbox_title', [
                'qty' => NumberHelper::toGermanFormat((string) ($approvable->entered_qty ?? $approvable->quantity), 4, trimTrailingZeros: true),
                'unit' => (string) ($approvable->entered_unit ?? ''),
                'article' => (string) ($approvable->variant->article->name ?? $approvable->variant->sku ?? '—'),
            ]),
            $viewer->can('viewAny', \App\Models\Inventory\Warehouse::class) ? route('inventory.stock', ['warehouse' => $approvable->warehouse->sqid]) : null,
            (string) __('inventory.negative.inbox_reference', [
                'warehouse' => (string) $approvable->warehouse->name,
                'name' => (string) ($approvable->requester->name ?? '—'),
            ]),
        );
    }

    public function decide(Approval $approval, User $actor, string $decision, ?string $reason, ?int $delegateUserId): void {
        $this->approval->decide($approval, $actor, $decision, $reason, $delegateUserId);
    }
}
