<?php
/*
 * Created on   : Mon Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractPartnerType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Contract;

/**
 * Vertragspartner-Bezug (Welle D, CLM): verknüpfter Kunde/Lieferant der
 * Organisation oder reiner Freitext-Partner.
 */
enum ContractPartnerType: string {
    case Customer = 'customer';
    case Supplier = 'supplier';
    case Other = 'other';

    public function label(): string {
        return match ($this) {
            self::Customer => (string) __('Kunde'),
            self::Supplier => (string) __('Lieferant'),
            self::Other => (string) __('Freitext'),
        };
    }
}
