<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportWarningScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Reporting\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Models\Platform\Organization;
use App\Services\Notification\DeadlineScans\{AbstractDeadlineScan, DeadlineScanOptions};
use App\Services\Notification\NotificationDispatcher;
use App\Services\Reporting\EarlyWarningService;

/**
 * Frühwarnungen mit Handlungsempfehlung an Teamleitung und Admin (MVP-889),
 * je Warnung einmal (Subjekt + Art).
 */
final class ReportWarningScan extends AbstractDeadlineScan {
    public function __construct(private readonly EarlyWarningService $warnings) {}

    public function key(): string {
        return 'report-warnings';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $sent = 0;
        foreach (Organization::query()->orderBy('id')->cursor() as $organization) {
            foreach ($this->warnings->collect($organization) as $warning) {
                if (! $warning->notify) {
                    continue;
                }
                $sent += $dispatcher->notify(NotificationEvent::ReportWarning, $warning->subject, null, [
                    'title' => (string) __($warning->title, $warning->params),
                    'title_key' => $warning->title,
                    'title_params' => $warning->params,
                    'message' => (string) __($warning->recommendation),
                    'message_key' => $warning->recommendation,
                    'message_params' => [],
                    'url' => $warning->url,
                ], 'warn:' . $warning->kind, dedup: true);
            }
        }

        return $sent;
    }
}
