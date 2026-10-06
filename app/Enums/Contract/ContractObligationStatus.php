<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractObligationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Contract;

use App\Enums\Contracts\HasLabel;

/**
 * Stand eines Vertragstermins (Welle D, CLM). Ohne Übergangstabelle: der
 * Fristen-Scan markiert offene Termine als versäumt, erledigen lässt sich
 * ein Termin aus jedem Stand — geprüft wird nur, ob er schon erledigt ist.
 */
enum ContractObligationStatus: string implements HasLabel {
    case Open = 'open';
    case Done = 'done';
    case Missed = 'missed';

    public function label(): string {
        return match ($this) {
            self::Open => (string) __('offen'),
            self::Done => (string) __('erledigt'),
            self::Missed => (string) __('versäumt'),
        };
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'info',
            self::Done => 'success',
            self::Missed => 'error',
        };
    }
}
