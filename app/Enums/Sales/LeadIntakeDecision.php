<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadIntakeDecision.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Sales;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Entscheidung über eine Aufnahme (Feature 200, MVP-1641) — mit Recht `lead.intake.decide` und Begründung. */
enum LeadIntakeDecision: string implements HasLabel {
    use HasOptions;

    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string {
        return (string) __('enums.sales.lead_intake_decision.' . $this->value);
    }

    public function tone(): string {
        return $this === self::Accepted ? 'success' : 'error';
    }
}
