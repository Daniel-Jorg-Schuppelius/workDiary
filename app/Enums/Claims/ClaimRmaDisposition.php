<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimRmaDisposition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Claims;

/** Verwendungsentscheidung eines Rückläufers (MVP-250). */
enum ClaimRmaDisposition: string {
    case Restock = 'restock';
    case Repair = 'repair';
    case ReturnToSupplier = 'return_to_supplier';
    case Scrap = 'scrap';
    case Dispose = 'dispose';

    public function label(): string {
        return match ($this) {
            self::Restock => (string) __('Wiedereinlagerung'),
            self::Repair => (string) __('Reparatur'),
            self::ReturnToSupplier => (string) __('Rücksendung an Lieferant'),
            self::Scrap => (string) __('Verschrottung'),
            self::Dispose => (string) __('Entsorgung'),
        };
    }
}
