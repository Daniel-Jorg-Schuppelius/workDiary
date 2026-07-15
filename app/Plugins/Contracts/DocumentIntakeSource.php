<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentIntakeSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Contracts;

use App\Models\CloudIntake\CloudDocumentConnection;
use App\Plugins\Support\Intake\{IntakeAccount, IntakeChangePage, IntakeContainer, IntakeItem};
use Psr\Http\Message\StreamInterface;

/**
 * Cloud-Dokumenteingang (Feature 080, MVP-351): gemeinsamer, LESENDER
 * Vertrag für Dropbox, Microsoft Graph und Google Drive. Adapter liefern
 * dieselben fachlichen Informationen; die Pipeline (`CloudIntakeRunner`)
 * bleibt providerneutral.
 *
 * Leitplanken (Konzept „Produktentscheidung und Abgrenzung"):
 *  - Identität sind Provider-IDs + Revision, nie Pfade.
 *  - Kein Schreiben/Löschen/Verschieben an der Quelle.
 *  - Webhooks sind nur Aufwecksignale — {@see intakeChanges()} über den
 *    persistierten Checkpoint ist die wiederanlaufbare Wahrheit.
 *  - Download-URLs sind kurzlebige Transportdetails und werden weder
 *    persistiert noch weitergereicht (deshalb Stream statt URL).
 *
 * Fehlerverhalten: Auth-/Scope-Probleme werfen die providerübliche
 * Exception; der Runner setzt die Verbindung auf `reauth_required`/`blocked`
 * und erzeugt einen Betriebsalarm statt still weiterzulaufen.
 */
interface DocumentIntakeSource {
    /** Bestätigte Kontoidentität der Verbindung (nach OAuth-Callback). */
    public function intakeAccount(CloudDocumentConnection $connection): IntakeAccount;

    /**
     * Wählbare Quell-Container (Drive/Bibliothek/Shared Drive/Namespace)
     * für die Admin-Auswahl.
     *
     * @return list<IntakeContainer>
     */
    public function intakeContainers(CloudDocumentConnection $connection): array;

    /**
     * Änderungen seit dem Checkpoint (null = Erstlauf ab Stammordner),
     * eine Seite je Aufruf. Ein ungültiger/abgelaufener Checkpoint wirft
     * {@see \App\Services\CloudIntake\StaleCheckpointException} — der Runner
     * startet dann einen begrenzten Vollabgleich, keinen blinden Neuimport.
     */
    public function intakeChanges(CloudDocumentConnection $connection, ?string $checkpoint): IntakeChangePage;

    /** Inhalt einer regulären Datei als Stream (Quarantäne-Download). */
    public function intakeDownload(CloudDocumentConnection $connection, IntakeItem $item): StreamInterface;
}
