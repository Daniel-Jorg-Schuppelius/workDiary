<?php
/*
 * Created on   : Fri Oct 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleDraftExternalCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Reselling;

/**
 * Serienlauf nach außen (Feature 185, MVP-1438): je Organisation mit aktivem
 * Schalter `resale.auto_external_drafts` ein Lauf von
 * {@see \App\Services\Reselling\Register\ResaleDraftRun} für Empfänger mit
 * externer Rechnungshoheit — eine Übergabe je Empfänger an sein
 * Buchhaltungsprogramm mit allem, was bis zum Vorlauf fällig wird. Monatlich
 * am Ersten im Zeitplan (Entscheidung des Inhabers 2026-10-30: monatlich,
 * Vorlauf 30 Tage), damit die Domains eines Kunden gesammelt auf eine Rechnung
 * kommen; idempotent über den Perioden-Stempel.
 */
class ResaleDraftExternalCommand extends ResaleDraftLocalCommand {
    protected $signature = 'resale:draft-external ' . self::ORGANIZATION_OPTION
        . ' {--dry-run : Nur zählen, nichts schreiben}'
        . ' {--force : Auch Organisationen ohne aktiven Serienlauf (einmaliger Lauf)}';

    protected $description = 'Serienrechnung: Übergaben an das Buchhaltungsprogramm aus fälligen Reselling-Perioden je Rechnungsempfänger mit externer Hoheit (Feature 185)';

    protected bool $external = true;
}
