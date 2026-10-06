<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisStatusPageService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Crisis;

use App\Enums\Crisis\{CrisisCaseStatus, CrisisCommunicationStatus};
use App\Models\Crisis\CrisisCommunication;
use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Services\CustomerPortal\Contracts\PortalNoticeSource;
use App\Services\CustomerPortal\Dto\PortalNotice;
use App\Services\Licensing\ModuleStatusResolver;
use App\Support\Auth\OrganizationAccessToken;
use App\Support\OrganizationContext;

/**
 * Öffentliche Statusseite einer Krise (MVP-915): versandte Mitteilungen an
 * die Öffentlichkeit über einen Token-Link, Mitteilungen an Kunden zusätzlich
 * auf der Startseite des Kundenportals. Gezeigt werden nur Betreff, Text und
 * Zeitpunkt — nie Titel oder Lage der Krisenakte. Nach der Entwarnung bleiben
 * die Mitteilungen noch RESOLVED_DAYS sichtbar.
 */
class CrisisStatusPageService extends OrganizationAccessToken implements PortalNoticeSource {
    public const HASH_KEY = 'crisis_status_token_hash';

    public const HINT_KEY = 'crisis_status_token_hint';

    public const ISSUED_KEY = 'crisis_status_token_issued_at';

    public const ENABLED_KEY = 'crisis_status_enabled';

    public const RESOLVED_DAYS = 7;

    public function __construct(private readonly ModuleStatusResolver $modules) {}

    /**
     * @param  list<string>  $audiences
     * @return list<PortalNotice>
     */
    public function notices(Organization $organization, array $audiences): array {
        if (! $this->modules->isActiveFor($organization, 'module.crisis_management')) {
            return [];
        }

        return OrganizationContext::run($organization, fn (): array => array_values(CrisisCommunication::query()
            ->with('crisisCase')
            ->whereIn('audience', $audiences)
            ->where('status', CrisisCommunicationStatus::Sent)
            ->whereHas('crisisCase', fn ($q) => $q->where(fn ($c) => $c
                ->whereIn('status', CrisisCaseStatus::active())
                ->orWhere('all_clear_at', '>=', now()->subDays(self::RESOLVED_DAYS))))
            ->orderByDesc('sent_at')
            ->limit(20)
            ->get()
            ->map(static fn (CrisisCommunication $c): PortalNotice => new PortalNotice(
                $c->subject,
                $c->body,
                $c->sent_at ?? now(),
                ...($c->crisisCase?->status->isActive() === true ? [] : ['tone' => 'success', 'badge' => (string) __('crisis.status_page.resolved')]),
            ))
            ->all()));
    }

    public function portalNotices(Organization $organization, Customer $customer): array {
        return $this->notices($organization, ['public', 'customers']);
    }
}
