<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevTransferKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\DatevOnline\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Was an DATEV ging: Buchungsstapel (EXTF) oder Belegbild. */
enum DatevTransferKind: string implements HasLabel {
    use HasOptions;

    case Extf = 'extf';
    case OutgoingDocument = 'outgoing_document';
    case IncomingDocument = 'incoming_document';

    public function label(): string {
        return (string) __('datev-online::datev.transfer_kind.' . $this->value);
    }

    /** Belegtyp in DATEV Unternehmen online. */
    public function documentType(): ?string {
        return match ($this) {
            self::OutgoingDocument => 'Rechnungsausgang',
            self::IncomingDocument => 'Rechnungseingang',
            self::Extf => null,
        };
    }
}
