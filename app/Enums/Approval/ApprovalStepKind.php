<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApprovalStepKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Approval;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Stufenart einer Freigabekette (`approver_rule.rule.kind`): die Stufe nennt
 * keine Person und keine Rolle, sondern ihre fachliche Art.
 */
enum ApprovalStepKind: string implements HasLabel {
    use HasOptions;

    case Commercial = 'commercial';
    case Technical = 'technical';
    case Hr = 'hr';
    case Management = 'management';

    public function label(): string {
        return (string) __('enums.approval.step-kind.' . $this->value);
    }

    /**
     * Einstellung „Stufenart → Rolle“ der Organisation (Entscheidung 2026-10-06).
     * Geschäftsleitungsstufen (Investitionsanträge) entscheidet nur die Akte.
     */
    public function roleSetting(): ?string {
        return $this === self::Management ? null : 'approvals.step_role.' . $this->value;
    }
}
