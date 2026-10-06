<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceDeadlineStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\AssetFinance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Stand einer Vertragsfrist (MVP-273). Ohne Übergangstabelle: „erledigt“
 * setzt die Akte aus jedem Stand, „versäumt“ der Fristen-Scan.
 */
enum AssetFinanceDeadlineStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case Done = 'done';
    case Missed = 'missed';

    public function label(): string {
        return (string) __('values.' . $this->value);
    }
}
