<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssueCreatedTrigger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\OpenIssue\Automation;

use App\Automation\Triggers\RuleTrigger;

final class OpenIssueCreatedTrigger implements RuleTrigger {
    public const KEY = 'openIssue.created';

    public function key(): string {
        return self::KEY;
    }

    public function label(): string {
        return (string) __('automation.trigger.open_issue_created');
    }

    /** @return array<string, mixed> */
    public function exampleConditions(): array {
        return ['all' => [['field' => 'severity', 'op' => 'in', 'value' => ['high', 'critical']]]];
    }
}
