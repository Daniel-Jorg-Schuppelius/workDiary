<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevBatchStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Status eines DATEV-Buchungsstapels (Feature 045, „Priorität 2 / Phase 3"):
 *
 *   draft → exported
 *
 * `draft`    – zusammengestellte Vorschau, noch keine Datei, Quellen NICHT
 *              verbraucht; jederzeit (weich) löschbar.
 * `exported` – finalisiert: CSV erzeugt, SHA-256 festgehalten, Quellen als
 *              übergeben markiert, unveränderlich (GoBD-Festschreibung möglich).
 *
 * Ein eigener Zwischenzustand `finalized` ist im MVP bewusst nicht nötig: die
 * Finalisierung erzeugt die Datei in einem Schritt. Der Enum bleibt für einen
 * späteren mehrstufigen Ablauf erweiterbar.
 */
enum DatevBatchStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Exported = 'exported';

    public function label(): string {
        return (string) __('enums.finance.datev-batch-status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Draft => 'ghost',
            self::Exported => 'success',
        };
    }

    /** Finalisiert und damit unveränderlich (Quellen verbraucht). */
    public function isFinal(): bool {
        return $this === self::Exported;
    }
}
