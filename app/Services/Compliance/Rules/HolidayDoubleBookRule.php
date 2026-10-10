<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HolidayDoubleBookRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance\Rules;

use App\Models\Schedule\ScheduledShift;
use App\Services\Calendar\HolidayService;
use App\Services\Compliance\{ComplianceRule, ComplianceViolation};
use App\Support\CarbonFmt;

/**
 * Warnt, wenn an einem Feiertag eine Schicht geplant wird — gesetzliche
 * Feiertage der Feiertagsregion und eigene Feiertage der Organisation.
 */
final class HolidayDoubleBookRule implements ComplianceRule {
    private ?HolidayService $holidays = null;

    public function key(): string {
        return 'holiday_double_book';
    }

    public function check(ScheduledShift $shift, array $settings): array {
        // Eine Instanz je Regel: Der Dienst merkt sich die Feiertage je Jahr.
        $this->holidays ??= app(HolidayService::class);
        $name = $this->holidays->nameFor($shift->date);
        if ($name === null) {
            return [];
        }

        return [
            new ComplianceViolation(
                code: 'holiday_double_book',
                severity: ComplianceViolation::SEVERITY_WARNING,
                message: __('Schicht liegt auf Feiertag „:name" (:date).', [
                    'name' => $name,
                    'date' => CarbonFmt::fdate($shift->date),
                ]),
                relatedShiftIds: [],
                context: ['holiday' => $name],
            ),
        ];
    }
}
