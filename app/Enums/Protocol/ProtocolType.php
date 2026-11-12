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

    /** Ob der Typ ohne Unterschrift nicht abgeschlossen werden kann. */
    public function requiresSignature(): bool {
        return $this->requiredSignatureRoles() !== [];
    }

    /**
     * Rollen, deren Unterschrift vor dem Abschluss vorliegen muss (MVP-1572):
     * Abnahme der Kunde, Übergabe beide Seiten.
     *
     * @return list<ProtocolSignatureRole>
     */
    public function requiredSignatureRoles(): array {
        return match ($this) {
            self::Acceptance => [ProtocolSignatureRole::Customer],
            self::Handover => [ProtocolSignatureRole::Customer, ProtocolSignatureRole::Contractor],
            default => [],
        };
    }
}
