<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExportRunState.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Export;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Lebenszyklus-Zustand eines Datentransfer-Export-Laufs.
 *
 *   preparing → ready | failed
 */
enum ExportRunState: string implements HasLabel {
    use HasOptions;

    case Preparing = 'preparing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string {
        return (string) __('export.state.' . $this->value);
    }

    public function isFinal(): bool {
        return $this === self::Ready || $this === self::Failed;
    }

    public function canDownload(): bool {
        return $this === self::Ready;
    }
}
