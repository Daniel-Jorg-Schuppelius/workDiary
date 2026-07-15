<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Contracts;

use App\Models\Backup\BackupTargetConnection;
use App\Plugins\Support\Backup\{BackupAccount, BackupRemoteObject};
use Psr\Http\Message\StreamInterface;

/**
 * Verschlüsselte Cloud-Backupziele (Feature 017 Phase 32, MVP-361):
 * gemeinsamer Vertrag für Dropbox, Microsoft Graph und Google Drive.
 *
 * Leitplanken (Konzept §BackupTarget-Vertrag):
 *  - Verbindungen sind SYSTEMWEIT (Plattform-Admin) und strikt von den
 *    Dokumentimport-Verbindungen getrennt — eigene Scopes, eigene Tokens.
 *  - Adapter arbeiten ausschließlich im WorkDiary-eigenen Backupbereich:
 *    kein Listen/Löschen fremder Inhalte.
 *  - Uploads laufen resumable und werden remote über die Größe verifiziert;
 *    die inhaltliche Verifikation (SHA-256) macht der Orchestrator über
 *    {@see backupDownload()}-Stichproben.
 *  - Es wird ausschließlich CIPHERTEXT hochgeladen — Klartext verlässt die
 *    Installation nie ({@see \App\Services\Backup\BackupCrypter}).
 *
 * Fehlerverhalten: Auth-/Quota-/Schreibprobleme werfen RuntimeException;
 * der Orchestrator übersetzt sie in Health-Status + Betriebsalarm.
 */
interface BackupTarget {
    /** Bestätigte Kontoidentität der Verbindung (nach OAuth-Callback). */
    public function backupAccount(BackupTargetConnection $connection): BackupAccount;

    /**
     * Verfügbarer Speicher, sofern der Provider ihn ausweist.
     *
     * @return array{total: int|null, used: int|null}
     */
    public function backupQuota(BackupTargetConnection $connection): array;

    /**
     * Stellt den Zielordner (Pseudonym-Pfad) im eigenen Backupbereich sicher
     * und liefert seine providerstabile Referenz.
     */
    public function backupEnsureFolder(BackupTargetConnection $connection, string $path): string;

    /**
     * Objekte unterhalb des eigenen Backupbereichs (Prefix = Pseudonym-Pfad).
     *
     * @return list<BackupRemoteObject>
     */
    public function backupList(BackupTargetConnection $connection, string $prefix): array;

    /**
     * Lädt eine lokale (bereits verschlüsselte) Datei resumable in den
     * Backupbereich und verifiziert die Remote-Größe; liefert die Remote-Ref.
     */
    public function backupUploadPart(BackupTargetConnection $connection, string $localPath, string $remoteName): string;

    /** Lädt ein Remote-Objekt (Verifikation/Restore) als Stream. */
    public function backupDownload(BackupTargetConnection $connection, string $remoteRef): StreamInterface;

    /** Löscht ein EIGENES Remote-Objekt (Retention); idempotent. */
    public function backupDelete(BackupTargetConnection $connection, string $remoteRef): bool;
}
