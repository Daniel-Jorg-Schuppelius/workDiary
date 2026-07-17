<?php
/*
 * Created on   : Wed Jul 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeExportStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\TimeExport;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Lebenszyklus einer Zeit-Export-Datei (MVP-019).
 *
 * - preparing: angefordert, Aggregation/Generierung läuft
 * - ready: Datei erzeugt, Hash berechnet, downloadbar
 * - delivered: an Lohnbüro/DATEV übermittelt (manuelle Bestätigung)
 * - rejected: zurückgewiesen (z. B. Lohnbüro meldet Mängel)
 * - superseded: durch späteren Re-Export desselben Zeitraums ersetzt
 */
enum TimeExportStatus: string implements HasLabel {
    use HasOptions;

    case Preparing = 'preparing';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Rejected = 'rejected';
    case Superseded = 'superseded';

    public function label(): string {
        return match ($this) {
            self::Preparing => __('In Vorbereitung'),
            self::Ready => __('Bereit'),
            self::Delivered => __('Übermittelt'),
            self::Rejected => __('Abgelehnt'),
            self::Superseded => __('Ersetzt'),
        };
    }

    public function isFinal(): bool {
        return $this === self::Delivered
            || $this === self::Rejected
            || $this === self::Superseded;
    }

    public function isDownloadable(): bool {
        return $this === self::Ready || $this === self::Delivered;
    }
}
