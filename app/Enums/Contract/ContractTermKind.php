<?php
/*
 * Created on   : Mon Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractTermKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Contract;

/**
 * Laufzeitmodell (Welle D, CLM): befristet mit Enddatum oder unbefristet
 * (nur mit Kündigungsfrist beendbar).
 */
enum ContractTermKind: string {
    case Fixed = 'fixed';
    case OpenEnded = 'open_ended';

    public function label(): string {
        return match ($this) {
            self::Fixed => (string) __('Befristet'),
            self::OpenEnded => (string) __('Unbefristet'),
        };
    }
}
