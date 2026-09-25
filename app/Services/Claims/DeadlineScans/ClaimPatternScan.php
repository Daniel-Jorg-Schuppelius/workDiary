<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimPatternScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Claims\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Models\Claims\ClaimCase;
use App\Models\Platform\Organization;
use App\Services\Claims\ClaimPatternDetector;
use App\Services\Notification\DeadlineScans\{AbstractDeadlineScan, DeadlineScanOptions};
use App\Services\Notification\NotificationDispatcher;
use Carbon\CarbonImmutable;

/**
 * Meldet neue Reklamationsmuster (MVP-886) an Teamleitung und Admin. Ein
 * Muster meldet sich einmal: Subjekt ist sein ältester Fall, Stufe die Regel.
 */
final class ClaimPatternScan extends AbstractDeadlineScan {
    public function __construct(private readonly ClaimPatternDetector $detector) {}

    public function key(): string {
        return 'claim-patterns';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $now = CarbonImmutable::now();

        return $this->sumPerOrganization(
            ClaimCase::query()->withoutGlobalScopes()->where('reported_at', '>=', $now->subDays(365)),
            function (Organization $organization) use ($dispatcher, $now): int {
                [$threshold, $days] = $this->detector->settingsFor($organization);
                $sent = 0;
                foreach ($this->detector->detect((int) $organization->id, $now->subDays($days), $now, $threshold) as $pattern) {
                    $subject = ClaimCase::query()->withoutGlobalScopes()->find($pattern['first_case_id']);
                    if ($subject === null) {
                        continue;
                    }
                    $params = ['label' => $pattern['label'], 'count' => $pattern['count'], 'days' => $days, 'rule' => __('claims.pattern.rule.' . $pattern['rule'])];
                    $sent += $dispatcher->notify(NotificationEvent::ClaimPattern, $subject, null, [
                        'title' => (string) __('claims.pattern.notify_title', $params),
                        'title_key' => 'claims.pattern.notify_title',
                        'title_params' => ['label' => $pattern['label']],
                        'message' => (string) __('claims.pattern.notify_message', $params),
                        'message_key' => 'claims.pattern.notify_message',
                        'message_params' => ['count' => $pattern['count'], 'days' => $days, 'rule' => ['key' => 'claims.pattern.rule.' . $pattern['rule'], 'fallback' => $pattern['rule']]],
                        'url' => $this->safeRoute('claims.reports.index'),
                    ], 'pattern:' . $pattern['rule'], dedup: true);
                }

                return $sent;
            },
        );
    }
}
