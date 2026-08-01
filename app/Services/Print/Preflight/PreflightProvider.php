<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PreflightProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Print\Preflight;

use App\Models\DocumentVersion;

/**
 * Vertrag für PDF-/Datei-Prüfwerkzeuge (MVP-459, austauschbar). Provider
 * erhalten ausschließlich die Dokumentversion aus dem Dokumentenspeicher —
 * niemals ungeprüfte Shell-Parameter. Externe Werkzeuge (z. B. veraPDF,
 * Ghostscript-Preflight) binden sich hier an; das Ergebnis wird vollständig
 * und unverändert am Druckauftrag gespeichert.
 */
interface PreflightProvider {
    /** Stabiler Provider-Name für Befund/Audit (z. B. "basic", "manual"). */
    public function name(): string;

    /** Kann dieser Provider die Datei prüfen (Mime/Endung)? */
    public function supports(DocumentVersion $version): bool;

    public function check(DocumentVersion $version): PreflightReport;
}
