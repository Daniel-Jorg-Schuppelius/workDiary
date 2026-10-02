<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevTransferStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\DatevOnline\Enums;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Stand einer Übertragung. Belege sind mit dem Upload übertragen; ein
 * EXTF-Stapel läuft als Importjob, bis DATEV `succeeded` oder `failed` meldet.
 */
enum DatevTransferStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Pending = 'pending';
    case Transferred = 'transferred';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string {
        return (string) __('datev-online::datev.transfer_status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Pending => 'info',
            self::Transferred, self::Succeeded => 'success',
            self::Failed => 'error',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Pending => [self::Succeeded, self::Failed, self::Transferred],
            // Ein fehlgeschlagener Versuch darf erneut laufen.
            self::Failed => [self::Pending, self::Transferred],
            self::Transferred, self::Succeeded => [],
        };
    }
}
