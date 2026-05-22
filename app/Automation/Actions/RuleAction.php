<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RuleAction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Automation\Actions;

use Illuminate\Database\Eloquent\Model;

interface RuleAction {
    /** Eindeutiger Typ-Schlüssel, der in `automation_rules.actions[*].type` referenziert wird. */
    public function type(): string;

    /**
     * Führt die Aktion gegen das Subject aus.
     *
     * @param  array<string, mixed>  $params  Aus der Rule-Definition übergebene Parameter
     * @return array<string, mixed>           Log-Eintrag (wird im RuleRun persistiert)
     */
    public function execute(Model $subject, array $params): array;
}
