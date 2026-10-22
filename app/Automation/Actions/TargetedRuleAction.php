<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TargetedRuleAction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Automation\Actions;

/**
 * Aktion mit einem Ziel, das die Regel festlegt (MVP-1273), etwa der Kurs
 * einer Einschreibung. Das Regelformular bietet die Ziele zur Auswahl an und
 * legt die Wahl als `params.target` ab.
 */
interface TargetedRuleAction extends RuleAction {
    public function targetLabel(): string;

    /**
     * Wählbare Ziele der Organisation, Wert → Bezeichnung.
     *
     * @return array<string, string>
     */
    public function targetOptions(int $organizationId): array;
}
