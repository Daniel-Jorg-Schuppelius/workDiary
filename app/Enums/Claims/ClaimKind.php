<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Claims;

/**
 * Anspruchsart (MVP-249): Garantie ist freiwillig (§ 443 BGB) und
 * schränkt die gesetzliche Gewährleistung (§§ 434 ff. BGB) nie ein.
 */
enum ClaimKind: string {
    case Guarantee = 'guarantee';
    case WarrantyLegal = 'warranty_legal';
    case WarrantyContractual = 'warranty_contractual';
    case Goodwill = 'goodwill';
    case TransportDamage = 'transport_damage';
    case UserError = 'user_error';
    case InternalError = 'internal_error';
    case SupplierFault = 'supplier_fault';
    case Unfounded = 'unfounded';

    public function label(): string {
        return match ($this) {
            self::Guarantee => (string) __('Garantie'),
            self::WarrantyLegal => (string) __('Gesetzliche Gewährleistung'),
            self::WarrantyContractual => (string) __('Vertragliche Gewährleistung'),
            self::Goodwill => (string) __('Kulanz'),
            self::TransportDamage => (string) __('Transportschaden'),
            self::UserError => (string) __('Fehlbedienung'),
            self::InternalError => (string) __('Interner Fehler'),
            self::SupplierFault => (string) __('Lieferantenfehler'),
            self::Unfounded => (string) __('Unbegründet'),
        };
    }
}
