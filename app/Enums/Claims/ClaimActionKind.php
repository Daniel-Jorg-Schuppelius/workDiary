<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimActionKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Claims;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Maßnahmenart (MVP-251) — Nacherfüllung (§ 439 BGB) zuerst. */
enum ClaimActionKind: string implements HasLabel {
    use HasOptions;

    case Rework = 'rework';
    case Repair = 'repair';
    case Replacement = 'replacement';
    case ServiceVisit = 'service_visit';
    case PriceReduction = 'price_reduction';
    case Refund = 'refund';
    case SupplierRecourse = 'supplier_recourse';
    case RootCauseFix = 'root_cause_fix';
    case Other = 'other';

    public function label(): string {
        return match ($this) {
            self::Rework => (string) __('enums.claims.claim_action_kind.rework'),
            self::Repair => (string) __('enums.claims.claim_action_kind.repair'),
            self::Replacement => (string) __('enums.claims.claim_action_kind.replacement'),
            self::ServiceVisit => (string) __('enums.claims.claim_action_kind.service_visit'),
            self::PriceReduction => (string) __('enums.claims.claim_action_kind.price_reduction'),
            self::Refund => (string) __('enums.claims.claim_action_kind.refund'),
            self::SupplierRecourse => (string) __('enums.claims.claim_action_kind.supplier_recourse'),
            self::RootCauseFix => (string) __('enums.claims.claim_action_kind.root_cause_fix'),
            self::Other => (string) __('enums.claims.claim_action_kind.other'),
        };
    }
}
