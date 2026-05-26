<?php
/*
 * Created on   : Wed Jul 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExportProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\TimeExport\Profiles;

use App\Models\TimeExport;

/**
 * Vertrag für ein Lohn-Export-Profil (MVP-019).
 *
 * Jedes Profil rendert eine vorbereitete {@see TimeExport}-Instanz
 * inklusive ihrer Zeilen in einen string-basierten Dateiinhalt.
 * Hashing, Speicherung und Statuswechsel macht der TimeExportService.
 */
interface ExportProfile {
    /** Stabiler Schlüssel (z. B. "generic", "datev", "lexware"). */
    public function key(): string;

    /** Datei-Endung ohne Punkt (z. B. "csv", "txt"). */
    public function format(): string;

    /**
     * Erzeugt den Dateiinhalt für einen vorbereiteten Export.
     * Die Zeilen müssen bereits in $export->lines vorliegen.
     */
    public function render(TimeExport $export): string;
}
