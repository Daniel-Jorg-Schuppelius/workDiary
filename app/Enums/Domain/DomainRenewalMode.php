<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainRenewalMode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Domain;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Verlängerungsmodus einer Domain (Feature 083). Provider-Werte des
 * DomainReselling-`RENEWALMODE`. `Autoexpire`/`Autodelete` sind
 * risikobehaftet und werden in Berichten/Kennzahlen hervorgehoben.
 */
enum DomainRenewalMode: string implements HasLabel {
    use HasOptions;

    case Autorenew = 'AUTORENEW';
    case Autoexpire = 'AUTOEXPIRE';
    case Autodelete = 'AUTODELETE';
    case Renewonce = 'RENEWONCE';

    public function label(): string {
        return (string) __('enums.domain.renewal_mode.' . strtolower($this->value));
    }

    /** Bewusstes Auslaufen/Löschen — für Risikokennzahlen. */
    public function isExpiring(): bool {
        return $this === self::Autoexpire || $this === self::Autodelete;
    }

    /** Toleranter Parser für Provider-Rohwerte (case-insensitive). */
    public static function fromProvider(?string $raw): ?self {
        if ($raw === null || $raw === '') {
            return null;
        }

        return self::tryFrom(strtoupper(trim($raw)));
    }
}
