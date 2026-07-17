<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalParty.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\ExternalParticipant;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art des externen Beteiligten (Feature 033): Subunternehmer, Prüfer,
 * Sachverständiger oder Sonstiges. Steuert nur die Darstellung/Filterung,
 * nicht die Rechte (diese stecken in den {@see ExternalAbility}-Flags).
 */
enum ExternalParty: string implements HasLabel {
    use HasOptions;

    case Subcontractor = 'subcontractor';
    case Inspector = 'inspector';
    case Expert = 'expert';
    case Other = 'other';

    public function label(): string {
        return __('external.party.' . $this->value);
    }

    /** @return list<self> */
    public static function selectable(): array {
        return self::cases();
    }
}
