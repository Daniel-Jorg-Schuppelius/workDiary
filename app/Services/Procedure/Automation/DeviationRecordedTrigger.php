<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeviationRecordedTrigger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Procedure\Automation;

use App\Automation\Triggers\RuleTrigger;

final class DeviationRecordedTrigger implements RuleTrigger {
    public const KEY = 'procedure.deviationRecorded';

    public function key(): string {
        return self::KEY;
    }

    public function label(): string {
        return (string) __('automation.trigger.procedure_deviation_recorded');
    }

    /** @return array<string, mixed> */
    public function exampleConditions(): array {
        return ['all' => [['field' => 'severity', 'op' => '=', 'value' => 'critical']]];
    }
}
