<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TransferStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Statusmaschine eines Übergabenachweises (Feature 045):
 *
 *   draft → confirmed → transferred
 *                     ↘ failed → confirmed (Retry)
 *   draft|confirmed → voided
 *
 * `transferred` ist final (Quellen sind verbraucht); Korrekturen laufen über
 * Storno-/Differenzübergaben (Teil B), nie über stilles Zurücksetzen.
 */
enum TransferStatus: string implements HasLabel, HasStatusTransitions {
    use \App\Enums\Concerns\HasTransitions;

    use HasOptions;

    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Transferred = 'transferred';
    case Failed = 'failed';
    case Voided = 'voided';

    public function label(): string {
        return (string) __('enums.finance.transfer-status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Draft => 'ghost',
            self::Confirmed => 'info',
            self::Transferred => 'success',
            self::Failed => 'error',
            self::Voided => 'ghost',
        };
    }

    /**
     * Erlaubte Folge-Status (State-Machine, geprüft im BillingTransferService).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Confirmed, self::Voided],
            self::Confirmed => [self::Transferred, self::Failed, self::Voided],
            self::Failed => [self::Confirmed],
            self::Transferred, self::Voided => [],
        };
    }

}
