<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskImportLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TaskSync;

use Carbon\CarbonImmutable;

/**
 * Projektzuordnung eines reinen Aufgabenimports (Jira, Asana): ein
 * Quellprojekt auf genau ein WorkDiary-Projekt. Umgesetzt über
 * {@see IsTaskImportLink}; die Spalten sind in beiden Plugins gleich.
 */
interface TaskImportLink extends TaskSyncLink {
    /** Grund einer Pause: von Hand oder durch Trennen der Verbindung (Wiederverbinden setzt fort). */
    public const PAUSED_MANUAL = 'manual';

    public const PAUSED_DISCONNECTED = 'disconnected';

    public function sourceProjectId(): string;

    public function targetProjectId(): int;

    /** Anzeigename des Quellprojekts (Name, sonst Schlüssel bzw. ID). */
    public function sourceLabel(): string;

    /** Bestätigt und nicht pausiert — nur dann läuft der Abgleich. */
    public function isRunnable(): bool;

    public function checkpointAt(): ?CarbonImmutable;

    /**
     * Laufstand festhalten; der Aufholpunkt rückt nur bei vollständigem Lauf vor.
     *
     * @param  array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int}  $counters
     */
    public function recordRun(array $counters, bool $complete, CarbonImmutable $startedAt, bool $full, ?string $error = null): void;
}
