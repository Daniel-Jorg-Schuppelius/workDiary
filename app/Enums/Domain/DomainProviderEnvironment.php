<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainProviderEnvironment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Domain;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Umgebung einer DomainReselling-Verbindung (Feature 083, MVP-385). OT&E ist
 * die Test-/Pilotumgebung; produktive Nutzung setzt einen bestandenen realen
 * Pilot voraus ({@see \App\Models\Domain\DomainProviderConnection::$pilot_confirmed_at}).
 */
enum DomainProviderEnvironment: string implements HasLabel {
    use HasOptions;

    case Ote = 'ote';
    case Production = 'production';

    public function label(): string {
        return (string) __('enums.domain.environment.' . $this->value);
    }

    public function isProduction(): bool {
        return $this === self::Production;
    }
}
