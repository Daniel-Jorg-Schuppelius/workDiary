<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleUnbilledWarningSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\EarlyWarnings;

use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Reporting\Contracts\EarlyWarningSource;
use App\Services\Reporting\Dto\EarlyWarning;
use App\Services\Reselling\Register\ResaleUnbilledReport;

/**
 * Frühwarnung (Phase 175): Rechnungsempfänger, deren Abos seit mehr als
 * {@see DAYS} Tagen ohne Rechnung laufen — aus dem Bericht „Ohne Rechnung“.
 */
final class ResaleUnbilledWarningSource implements EarlyWarningSource {
    public const int DAYS = 90;

    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly ResaleUnbilledReport $report,
    ) {}

    public function key(): string {
        return 'resale_unbilled';
    }

    public function warnings(Organization $organization): array {
        if (! $this->features->routeEnabled('finance.resale.index')) {
            return [];
        }
        /** @var array<int, array{customer: Customer, periods: int, days: int}> $byRecipient */
        $byRecipient = [];
        foreach ($this->report->build(self::DAYS) as $row) {
            $recipient = $row['subscription']->billedTo();
            if ($recipient === null) {
                continue; // ohne Halter: Aufgabe „Abos ohne Halter“, keine Forderung
            }
            $current = $byRecipient[$recipient->id] ?? ['customer' => $recipient, 'periods' => 0, 'days' => 0];
            $current['periods'] += $row['open_periods'];
            $current['days'] = max($current['days'], $row['days']);
            $byRecipient[$recipient->id] = $current;
        }
        usort($byRecipient, static fn(array $a, array $b): int => $b['days'] <=> $a['days']);

        return array_map(static fn(array $r): EarlyWarning => new EarlyWarning(
            kind: 'resale_unbilled',
            subject: $r['customer'],
            title: 'reporting.warning.resale_unbilled.title',
            detail: 'reporting.warning.resale_unbilled.detail',
            recommendation: 'reporting.warning.resale_unbilled.recommendation',
            url: route('finance.resale.reconcile.show', $r['customer']),
            params: ['name' => (string) $r['customer']->name, 'count' => $r['periods'], 'days' => $r['days']],
        ), $byRecipient);
    }
}
