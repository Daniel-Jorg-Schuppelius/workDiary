<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeHandoverStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Lexoffice;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Übergabestand eines lokal ausgestellten Belegs an Lexware (Feature 158,
 * MVP-833): getrennt vom Rechnungs- und Versandstatus. „Exportiert" heißt
 * heruntergeladen, „bestätigt" heißt manuell mit Benutzer und Zeitpunkt
 * quittiert, „übertragen" heißt über die Schnittstelle zugeordnet — keiner
 * dieser Stände bedeutet „gebucht" oder „bezahlt".
 */
enum LexofficeHandoverStatus: string implements HasLabel {
    use HasOptions;

    case Pending = 'pending';
    case Exported = 'exported';
    case Confirmed = 'confirmed';
    case Transferred = 'transferred';
    case NeedsReview = 'needs_review';
    case Failed = 'failed';

    public function label(): string {
        return (string) __('lexware.handover.status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Pending => 'ghost',
            self::Exported => 'info',
            self::Confirmed, self::Transferred => 'success',
            self::NeedsReview => 'warning',
            self::Failed => 'error',
        };
    }

    /** Übergabe gilt als erledigt — kein weiterer Export nötig. */
    public function isSettled(): bool {
        return in_array($this, [self::Confirmed, self::Transferred], true);
    }
}
