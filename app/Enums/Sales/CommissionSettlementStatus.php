<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionSettlementStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Sales;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Stand eines Provisions-Abrechnungslaufs (Feature 146). `Draft` ist die
 * Vorschau und jederzeit neu berechenbar; `Closed` ist festgeschrieben —
 * ab dann korrigiert nur noch eine Rueckrechnung in einem spaeteren Lauf.
 */
enum CommissionSettlementStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Closed = 'closed';

    public function label(): string {
        return match ($this) {
            self::Draft => __('commission.run_status.draft'),
            self::Closed => __('commission.run_status.closed'),
        };
    }

    public function tone(): string {
        return $this === self::Draft ? 'warning' : 'success';
    }

    /** Nur der Entwurf laesst sich neu berechnen, ergaenzen oder loeschen. */
    public function isEditable(): bool {
        return $this === self::Draft;
    }
}
