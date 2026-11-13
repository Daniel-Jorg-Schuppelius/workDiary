<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiApprovalState.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Ai;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Stand der Datenschutzfreigabe einer KI-Verbindung (Feature 025,
 * MVP-1672). Abgeleitet aus Prüfdatum, Angaben und Frist der Organisation,
 * nicht gespeichert — deshalb kein Lebenszyklus mit Übergängen.
 */
enum AiApprovalState: string implements HasLabel {
    use HasOptions;

    case Approved = 'approved';
    /** Freigegeben, die Frist endet aber bald (Erinnerung im Arbeitsvorrat). */
    case Expiring = 'expiring';
    case Expired = 'expired';
    /** Nie freigegeben oder widerrufen. */
    case Missing = 'missing';

    public function label(): string {
        return (string) __('enums.ai.approval_state.' . $this->value);
    }

    public function isApproved(): bool {
        return $this === self::Approved || $this === self::Expiring;
    }

    public function badge(): string {
        return match ($this) {
            self::Approved => 'success',
            self::Expiring => 'warning',
            self::Expired => 'error',
            self::Missing => 'neutral',
        };
    }
}
