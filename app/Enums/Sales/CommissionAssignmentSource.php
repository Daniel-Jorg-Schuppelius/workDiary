<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionAssignmentSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Sales;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Herkunft der Zuordnung Beleg → Vertriebsperson (Feature 146): entweder aus
 * der Lead-Pipeline (Feature 091 — der konvertierte Lead traegt den
 * Verantwortlichen) oder von Hand am Beleg gesetzt. Die Herkunft steht in der
 * Provisionszeile, damit im Nachhinein erkennbar bleibt, warum jemand die
 * Provision bekommen hat.
 */
enum CommissionAssignmentSource: string implements HasLabel {
    use HasOptions;

    case Lead = 'lead';
    case Manual = 'manual';

    public function label(): string {
        return match ($this) {
            self::Lead => __('commission.assignment.lead'),
            self::Manual => __('commission.assignment.manual'),
        };
    }
}
