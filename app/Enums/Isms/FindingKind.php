<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FindingKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art einer Auditfeststellung (Feature 046, Inkrement C):
 * Haupt-/Nebenabweichung (Nichtkonformität), Beobachtung oder
 * Verbesserungspotenzial. Nichtkonformitäten ({@see self::isNonconformity()})
 * dürfen NUR geschlossen werden, wenn mindestens eine Korrekturmaßnahme
 * als wirksam geprüft wurde (AuditService).
 */
enum FindingKind: string implements HasLabel {
    use HasOptions;

    case NonconformityMajor = 'nonconformityMajor';
    case NonconformityMinor = 'nonconformityMinor';
    case Observation = 'observation';
    case Improvement = 'improvement';

    public function label(): string {
        return (string) __('enums.isms.finding-kind.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::NonconformityMajor => 'error',
            self::NonconformityMinor => 'warning',
            self::Observation => 'info',
            self::Improvement => 'success',
        };
    }

    /** Nichtkonformität (major/minor) — verschärfte Abschlussregel. */
    public function isNonconformity(): bool {
        return in_array($this, [self::NonconformityMajor, self::NonconformityMinor], true);
    }
}
