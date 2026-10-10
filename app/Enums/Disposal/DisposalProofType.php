<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisposalProofType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Disposal;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Nachweistyp der Entsorger-Übergabe (Feature 100, MVP-475). workDiary
 * referenziert/archiviert die Belege (inkl. eANV-Registerbezug), erzeugt
 * sie aber nicht — keine Behördenrolle.
 */
enum DisposalProofType: string implements HasLabel {
    use HasOptions;

    case TransferNote = 'transfer_note';
    case ConsignmentNote = 'consignment_note';
    case DisposalCertificate = 'disposal_certificate';
    case Eanv = 'eanv';
    case DisposerCertificate = 'disposer_certificate';

    public function label(): string {
        return match ($this) {
            self::TransferNote => (string) __('enums.disposal.disposal_proof_type.transfer_note'),
            self::ConsignmentNote => (string) __('enums.disposal.disposal_proof_type.consignment_note'),
            self::DisposalCertificate => (string) __('enums.disposal.disposal_proof_type.disposal_certificate'),
            self::Eanv => (string) __('enums.disposal.disposal_proof_type.eanv'),
            self::DisposerCertificate => (string) __('enums.disposal.disposal_proof_type.disposer_certificate'),
        };
    }
}
