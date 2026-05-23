<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Protocol;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum ProtocolType: string implements HasLabel {
    use HasOptions;

    case Acceptance = 'acceptance';
    case Service = 'service';
    case Maintenance = 'maintenance';
    case Handover = 'handover';
    case Defect = 'defect';
    case Inspection = 'inspection';
    case SiteVisit = 'siteVisit';
    case Other = 'other';

    public function label(): string {
        return (string) __('enums.protocol.type.' . $this->value);
    }

    /**
     * Ob der Typ eine Pflicht-Signatur (mindestens einer Rolle) erfordert.
     * Wird vom Service zum Pruefen vor `sign`/`signDirect` genutzt; in MVP-020
     * nur informativ, in MVP-022 wird die Pflicht eingefordert.
     */
    public function requiresSignature(): bool {
        return in_array($this, [self::Acceptance, self::Handover], true);
    }
}
