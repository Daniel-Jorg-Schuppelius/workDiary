<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\WorkQueue;

use App\Enums\Invoicing\IncomingInvoiceTransferStatus;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Document\Document;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Reiter des Rechnungseingangs mit Handarbeit (Zuordnen, Prüfen, fehlgeschlagene
 * Übergabe, mögliche Dubletten) — gebündelt, sichtbar wie die Liste selbst.
 */
final class IncomingInvoiceWorkItemSource implements WorkItemSource {
    private const ROUTE = 'finance.incoming-invoices.index';

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'incoming_invoice';
    }

    public function label(): string {
        return (string) __('work-queue.source.incoming_invoice');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Inbox;
    }

    /** Bearbeiten darf nur, wer Abrechnung verwaltet; Lesende haben hier keine Arbeit. */
    public function available(User $viewer): bool {
        return $this->features->routeEnabled(self::ROUTE)
            && Gate::forUser($viewer)->allows('viewAny', Document::class)
            && $viewer->canManageBilling();
    }

    public function items(User $viewer, Organization $organization): iterable {
        foreach (['assign', 'review', 'failed', 'duplicates'] as $tab) {
            $query = IncomingEInvoice::query()->visibleTo($viewer)->where('organization_id', $organization->id);
            match ($tab) {
                'assign' => $query->awaitingAssignment(),
                'review' => $query->awaitingReview(),
                'failed' => $query->inTransfer([IncomingInvoiceTransferStatus::Failed]),
                'duplicates' => $query->openDuplicates(),
            };
            $count = (clone $query)->count();
            if ($count === 0) {
                continue;
            }
            $due = in_array($tab, ['assign', 'review'], true) ? $query->min('due_date') : null;
            yield new WorkItem(
                source: $this->key(),
                key: 'incoming_invoice:' . $tab,
                title: (string) __('work-queue.item.incoming_invoice.' . $tab),
                url: route(self::ROUTE, ['tab' => $tab]),
                dueAt: $due !== null ? CarbonImmutable::parse((string) $due) : null,
                count: $count,
            );
        }
    }
}
