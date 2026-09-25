<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimPatternWarningSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Claims\EarlyWarnings;

use App\Models\Claims\ClaimCase;
use App\Models\Platform\Organization;
use App\Services\Claims\ClaimPatternDetector;
use App\Services\Reporting\Contracts\EarlyWarningSource;
use App\Services\Reporting\Dto\EarlyWarning;
use Carbon\CarbonImmutable;

/**
 * Reklamationsmuster als Frühwarnung (MVP-889). Benachrichtigt wird über den
 * eigenen Scan aus MVP-886, deshalb `notify: false`.
 */
final class ClaimPatternWarningSource implements EarlyWarningSource {
    public function __construct(private readonly ClaimPatternDetector $detector) {}

    public function key(): string {
        return 'claim-patterns';
    }

    public function warnings(Organization $organization): array {
        [$threshold, $days] = $this->detector->settingsFor($organization);
        $now = CarbonImmutable::now();
        $warnings = [];
        foreach ($this->detector->detect((int) $organization->id, $now->subDays($days), $now, $threshold) as $pattern) {
            $subject = ClaimCase::query()->find($pattern['first_case_id']);
            if ($subject === null) {
                continue;
            }
            $warnings[] = new EarlyWarning(
                kind: 'claim_pattern',
                subject: $subject,
                title: 'claims.pattern.notify_title',
                detail: 'claims.pattern.notify_message',
                recommendation: 'reporting.warning.claim_pattern.recommendation',
                url: route('claims.reports.index'),
                notify: false,
                params: ['label' => $pattern['label'], 'count' => $pattern['count'], 'days' => $days, 'rule' => (string) __('claims.pattern.rule.' . $pattern['rule'])],
            );
        }

        return $warnings;
    }
}
