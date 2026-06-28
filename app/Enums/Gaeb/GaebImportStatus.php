<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GaebImportStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Gaeb;

/**
 * Status eines GAEB-Importlaufs (Feature 049, MVP-081).
 *
 * - Pending:        Datei hochgeladen, Preflight noch nicht abgeschlossen
 * - PreflightFailed: Preflight hat blockierende Fehler gefunden, kein Schreiben
 * - Imported:       LV wurde erzeugt/aktualisiert
 * - Conflict:       Reimport würde Positionen mit Ausführungsbezug berühren
 */
enum GaebImportStatus: string {
    case Pending = 'pending';
    case PreflightFailed = 'preflight_failed';
    case Imported = 'imported';
    case Conflict = 'conflict';

    public function label(): string {
        return __('gaeb.import.status.' . $this->value);
    }
}
