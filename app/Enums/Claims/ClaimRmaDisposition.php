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

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Verwendungsentscheidung eines Rückläufers (MVP-250). */
enum ClaimRmaDisposition: string implements HasLabel {
    use HasOptions;

    case Restock = 'restock';
    case Repair = 'repair';
    case ReturnToSupplier = 'return_to_supplier';
    case Scrap = 'scrap';
    case Dispose = 'dispose';

    public function label(): string {
        return match ($this) {
            self::Restock => (string) __('enums.claims.claim_rma_disposition.restock'),
            self::Repair => (string) __('enums.claims.claim_rma_disposition.repair'),
            self::ReturnToSupplier => (string) __('enums.claims.claim_rma_disposition.return_to_supplier'),
            self::Scrap => (string) __('enums.claims.claim_rma_disposition.scrap'),
            self::Dispose => (string) __('enums.claims.claim_rma_disposition.dispose'),
        };
    }
}
