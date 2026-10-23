<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceInquiryWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\WorkQueue;

use App\Enums\Invoicing\InvoiceInquiryStatus;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Invoicing\InvoiceInquiry;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use Illuminate\Support\Facades\Gate;

/** Unbeantwortete Rechnungsnachfragen aus E-Mails (Feature 166). */
final class InvoiceInquiryWorkItemSource implements WorkItemSource {
    private const ROUTE = 'invoices.inquiries.index';

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'invoice_inquiry';
    }

    public function label(): string {
        return (string) __('work-queue.source.invoice_inquiry');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Inbox;
    }

    public function available(User $viewer): bool {
        return $this->features->routeEnabled(self::ROUTE) && Gate::forUser($viewer)->allows('viewAny', InvoiceInquiry::class);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $open = InvoiceInquiry::query()
            ->where('organization_id', $organization->id)
            ->where('status', InvoiceInquiryStatus::Open)
            ->orderBy('received_at')
            ->get();

        foreach ($open as $inquiry) {
            yield new WorkItem(
                source: $this->key(),
                key: $inquiry->getMorphClass() . ':' . $inquiry->id,
                title: (string) __('work-queue.item.invoice_inquiry', ['number' => $inquiry->invoice_number]),
                url: route(self::ROUTE),
                detail: $inquiry->from_name ?: $inquiry->from_email,
            );
        }
    }
}
