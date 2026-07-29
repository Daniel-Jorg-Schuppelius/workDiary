<?php
/*
 * Created on   : Tue Jul 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteTimeWriter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

/**
 * Schreibender Zugriff eines Zeit-Plugins auf seinen Fremdbestand.
 *
 * Implementiert von den API-Clients (Toggl, Kimai, Clockify, OpenProject);
 * {@see TimeWritebackDispatcher} arbeitet ausschließlich gegen diesen Vertrag,
 * sodass Konflikterkennung und Outbox-Semantik nur einmal existieren.
 *
 * `$context` ist der Payload der {@see \App\Models\ExternalReference} des
 * Eintrags (Workspace-/Projekt-IDs u. ä.) — je Plugin verschieden, deshalb
 * nicht Teil der Signatur.
 *
 * @phpstan-type LocalTimeState array{description: ?string, date: ?\Carbon\CarbonImmutable, started_at: ?\Carbon\CarbonImmutable, ended_at: ?\Carbon\CarbonImmutable, minutes: int, billable: bool}
 */
interface RemoteTimeWriter {
    /**
     * Aktueller Fremdstand — in derselben Form, in der der lokale übergeben wird.
     *
     * Bewusst die Werte statt eines Abdrucks: nur so kann der Konfliktfall in der
     * Inbox zeigen, **was** drüben anders ist, und „Fremdstand übernehmen"
     * tatsächlich etwas übernehmen.
     *
     * `null` bedeutet „nicht ermittelbar" (Netzfehler, fehlende Rechte) — der
     * Aufrufer darf daraus **keinen** Konflikt ableiten. Ein drüben gelöschter
     * Eintrag liefert ebenfalls `null`; die Löschung erkennt der Import.
     *
     * @param  array<string, mixed>  $context
     * @return LocalTimeState|null
     */
    public function fetchRemoteState(string $externalId, array $context): ?array;

    /**
     * Überträgt den lokalen Stand.
     *
     * @param  LocalTimeState  $entry
     * @param  array<string, mixed>  $context
     * @return bool  false → die Outbox wiederholt den Versuch
     */
    public function pushEntryUpdate(string $externalId, array $entry, array $context): bool;

    /**
     * Fingerabdruck des lokalen Stands in der Konvention **dieses** Plugins —
     * nach erfolgreichem Schreiben ist er der neue Fremdstand.
     *
     * Muss zu {@see fetchRemoteFingerprint()} passen; Systeme ohne Start-/
     * Stoppzeiten (OpenProject: Datum + Dauer) bilden ihn deshalb anders.
     *
     * @param  LocalTimeState  $entry
     * @param  array<string, mixed>  $context
     */
    public function fingerprintOf(array $entry, array $context): ?string;

    /**
     * Löscht den Eintrag im Fremdsystem. Ein bereits gelöschter Eintrag gilt
     * als Erfolg — das Ziel ist erreicht.
     *
     * @param  array<string, mixed>  $context
     */
    public function pushEntryDeletion(string $externalId, array $context): bool;
}
