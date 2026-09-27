<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecallPortalNotices.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\{RecallItemStatus, RecallStatus};
use App\Models\Customer\Customer;
use App\Models\Inventory\Recall;
use App\Models\Platform\Organization;
use App\Services\CustomerPortal\Contracts\PortalNoticeSource;
use App\Services\CustomerPortal\Dto\PortalNotice;
use App\Services\Licensing\ModuleStatusResolver;
use App\Support\OrganizationContext;

/**
 * Rückrufe im Kundenportal (MVP-922): aktive Rückrufe mit noch nicht
 * erledigten Positionen dieses Kunden. Gezeigt wird die Kundennachricht,
 * nie der interne Grund.
 */
final class RecallPortalNotices implements PortalNoticeSource {
    public function __construct(private readonly ModuleStatusResolver $modules) {}

    public function portalNotices(Organization $organization, Customer $customer): array {
        if (! $this->modules->isActiveFor($organization, 'module.lager')) {
            return [];
        }

        return OrganizationContext::run($organization, fn (): array => array_values(Recall::query()
            ->with('variant.article')
            ->where('status', RecallStatus::Active->value)
            ->whereHas('items', fn ($q) => $q->where('customer_id', $customer->id)->where('status', '!=', RecallItemStatus::Resolved->value))
            ->orderByDesc('activated_at')
            ->get()
            ->map(static fn (Recall $recall): PortalNotice => new PortalNotice(
                (string) __('recall.portal.subject', ['title' => $recall->title, 'product' => (string) ($recall->variant->article->name ?? '')]),
                $recall->customer_message ?? (string) __('recall.mail.default_message'),
                $recall->activated_at ?? now(),
            ))
            ->all()));
    }
}
