<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureDeviationProposedAction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Procedure;

use App\Enums\Contracts\HasLabel;

enum ProcedureDeviationProposedAction: string implements HasLabel {
    case None = 'none';
    case OpenIssue = 'open_issue';
    case NewDiaryEntry = 'new_diary_entry';
    case Requalify = 'requalify';
    case Escalate = 'escalate';

    public function label(): string {
        return (string) __('enums.procedure.deviation-proposed-action.' . $this->value);
    }
}
