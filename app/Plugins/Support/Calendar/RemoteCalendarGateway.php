<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteCalendarGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Calendar;

/**
 * Gateway-Vertrag der REST-Kalender-Provider (MVP-328, Bauturbo A8):
 * Microsoft Graph und Google Calendar implementieren Anlegen/Ändern/Löschen
 * einzelner Events plus Kalenderliste (Ziel-Auswahl + billige Health-Probe).
 *
 * Fehlersemantik wie beim CalDAV-Gateway: Transport-/HTTP-Fehler werden zu
 * `null`/`false` — der {@see RemoteCalendarPublishService} zählt sie als
 * `failed` und versucht sie beim nächsten Lauf erneut.
 */
interface RemoteCalendarGateway {
    /**
     * Legt das Event im Ziel-Kalender an (idempotent über transactionId bzw.
     * deterministische Event-ID) und liefert die Remote-ID — `null` bei Fehler.
     */
    public function createEvent(RemoteCalendarEvent $event): ?string;

    /** Aktualisiert das Remote-Event; `false` bei Fehler. */
    public function updateEvent(string $remoteId, RemoteCalendarEvent $event): bool;

    /** Entfernt das Remote-Event; „bereits entfernt" (404/410) ist idempotenter Erfolg. */
    public function deleteEvent(string $remoteId): bool;

    /**
     * Verfügbare Ziel-Kalender des verbundenen Kontos.
     *
     * @return list<array{id: string, name: string}>
     *
     * @throws \RuntimeException bei HTTP-Fehlern (nur Status + Pfad, nie Token)
     */
    public function listCalendars(): array;

    /** Billige Health-Probe: Kalenderliste abrufbar? */
    public function ping(): bool;
}
