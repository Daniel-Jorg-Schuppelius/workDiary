<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityExcerptService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sustainability;

use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Models\Sustainability\{SustainabilityReportSnapshot, SustainabilityTarget};
use App\Services\CustomerPortal\Contracts\PortalNoticeSource;
use App\Services\CustomerPortal\Dto\PortalNotice;
use App\Services\Licensing\ModuleStatusResolver;
use App\Support\Auth\OrganizationAccessToken;
use App\Support\OrganizationContext;
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Nachhaltigkeitsauszug (MVP-930): ein freigegebener, eingefrorener
 * Berichts-Snapshot (feste Zahlen statt Live-Werte) und auf Wunsch die Ziele —
 * öffentlich über einen Token-Link und als Hinweis im Kundenportal. Ohne
 * Konformitäts- oder Klimaneutralitätsbehauptung.
 */
class SustainabilityExcerptService extends OrganizationAccessToken implements PortalNoticeSource {
    public const HASH_KEY = 'sustainability_excerpt_token_hash';

    public const HINT_KEY = 'sustainability_excerpt_token_hint';

    public const ISSUED_KEY = 'sustainability_excerpt_token_issued_at';

    public const ENABLED_KEY = 'sustainability_excerpt_enabled';

    public const PUBLISH_KEY = 'sustainability_excerpt';

    public function __construct(private readonly ModuleStatusResolver $modules) {}

    public function publish(Organization $organization, ?SustainabilityReportSnapshot $snapshot, bool $withTargets, ?string $statement = null): void {
        $settings = (array) ($organization->settings ?? []);
        $settings[self::PUBLISH_KEY] = ['snapshot_id' => $snapshot?->id, 'targets' => $withTargets, 'statement' => $statement];
        $organization->forceFill(['settings' => $settings])->save();
    }

    /** @return array{snapshot_id: ?int, targets: bool, statement: ?string} */
    public function publication(Organization $organization): array {
        $raw = (array) data_get($organization->settings, self::PUBLISH_KEY, []);

        return ['snapshot_id' => isset($raw['snapshot_id']) ? (int) $raw['snapshot_id'] : null, 'targets' => (bool) ($raw['targets'] ?? false), 'statement' => isset($raw['statement']) && $raw['statement'] !== '' ? (string) $raw['statement'] : null];
    }

    /** @return array{snapshot: SustainabilityReportSnapshot, targets: list<SustainabilityTarget>, statement: ?string, offsets: list<\App\Models\Sustainability\SustainabilityOffset>}|null */
    public function excerpt(Organization $organization): ?array {
        if (! $this->modules->isActiveFor($organization, 'module.sustainability')) {
            return null;
        }
        $publication = $this->publication($organization);

        return OrganizationContext::run($organization, static function () use ($organization, $publication): ?array {
            $snapshot = $publication['snapshot_id'] !== null
                ? SustainabilityReportSnapshot::query()->where('organization_id', $organization->id)->find($publication['snapshot_id'])
                : null;
            if (! $snapshot instanceof SustainabilityReportSnapshot) {
                return null;
            }

            return [
                'snapshot' => $snapshot,
                'targets' => $publication['targets'] ? array_values(SustainabilityTarget::query()->where('organization_id', $organization->id)->orderBy('target_year')->get()->all()) : [],
                'statement' => $publication['statement'],
                // Nachweise (MVP-961) des Berichtszeitraums, getrennt ausgewiesen.
                'offsets' => array_values(\App\Models\Sustainability\SustainabilityOffset::query()->where('organization_id', $organization->id)
                    ->whereBetween('claim_year', [(int) $snapshot->period_start->year, (int) $snapshot->period_end->year])->orderBy('claim_year')->get()->all()),
            ];
        });
    }

    public function portalNotices(Organization $organization, Customer $customer): array {
        $excerpt = $this->excerpt($organization);
        if ($excerpt === null) {
            return [];
        }
        $snapshot = $excerpt['snapshot'];
        $tonnes = NumberHelper::toGermanFormat(((float) ($snapshot->data['co2e_total_kg'] ?? 0)) / 1000, 1, withThousandsSeparator: true);

        return [new PortalNotice(
            (string) __('sustainability.excerpt.portal_subject', ['from' => $snapshot->period_start->format('d.m.Y'), 'to' => $snapshot->period_end->format('d.m.Y')]),
            (string) __('sustainability.excerpt.portal_body', ['tonnes' => $tonnes]),
            $snapshot->created_at ?? now(),
            'info',
        )];
    }
}
