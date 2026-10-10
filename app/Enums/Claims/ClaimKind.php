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

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Anspruchsart (MVP-249): Garantie ist freiwillig (§ 443 BGB) und
 * schränkt die gesetzliche Gewährleistung (§§ 434 ff. BGB) nie ein.
 */
enum ClaimKind: string implements HasLabel {
    use HasOptions;

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
            self::Guarantee => (string) __('enums.claims.claim_kind.guarantee'),
            self::WarrantyLegal => (string) __('enums.claims.claim_kind.warranty_legal'),
            self::WarrantyContractual => (string) __('enums.claims.claim_kind.warranty_contractual'),
            self::Goodwill => (string) __('enums.claims.claim_kind.goodwill'),
            self::TransportDamage => (string) __('enums.claims.claim_kind.transport_damage'),
            self::UserError => (string) __('enums.claims.claim_kind.user_error'),
            self::InternalError => (string) __('enums.claims.claim_kind.internal_error'),
            self::SupplierFault => (string) __('enums.claims.claim_kind.supplier_fault'),
            self::Unfounded => (string) __('enums.claims.claim_kind.unfounded'),
        };
    }
}
