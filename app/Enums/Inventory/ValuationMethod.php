<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ValuationMethod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Inventory;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Bewertungsverfahren der Bestandsführung (Feature 048, E3). Gleitender
 * Durchschnitt (MVP-070) oder FIFO über Zugangsschichten. Je Organisation
 * konfigurierbar; das gewählte Verfahren bestimmt, welche Bewertungsstrategie
 * Zu- und Abgänge fortschreibt.
 */
enum ValuationMethod: string implements HasLabel {
    use HasOptions;

    case MovingAverage = 'moving_average';
    case Fifo = 'fifo';
    case Fefo = 'fefo';

    public function label(): string {
        return __('inventory.valuation.method.' . $this->value);
    }
}
