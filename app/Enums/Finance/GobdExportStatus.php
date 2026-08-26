<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GobdExportStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Lauf-Status einer GoBD-Z3-Datenträgerüberlassung (Feature 063, MVP-722):
 *
 *   queued → running → ready | failed
 *
 * Seit dem Umbau auf die Queue (Vollscan 2026-08-23, A16) entsteht das Paket
 * nicht mehr im HTTP-Request. Der Nachweis (`gobd_exports`) wird deshalb schon
 * beim Einreihen angelegt — ein Lauf ist ab der ersten Sekunde sichtbar, und
 * ein gescheiterter bleibt als `failed` mit Fehlertext stehen, statt spurlos
 * zu verschwinden. Bestandszeilen aus der synchronen Zeit sind `ready`.
 */
enum GobdExportStatus: string implements HasLabel {
    use HasOptions;

    case Queued = 'queued';
    case Running = 'running';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string {
        return (string) __('enums.finance.gobd-export-status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Queued => 'ghost',
            self::Running => 'info',
            self::Ready => 'success',
            self::Failed => 'error',
        };
    }

    /** Paket liegt vor und ist herunterladbar. */
    public function isDownloadable(): bool {
        return $this === self::Ready;
    }

    /** Lauf noch unterwegs (Kachel zeigt Fortschritt statt Download). */
    public function isPending(): bool {
        return $this === self::Queued || $this === self::Running;
    }
}
