<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSession.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\RemoteSupport\Providers;

use Carbon\CarbonImmutable;

/**
 * Normalisierte Repräsentation einer einzelnen Fernwartungs-Verbindung,
 * unabhängig vom konkreten Anbieter (AnyDesk/TeamViewer). Die Provider-Clients
 * mappen ihre Roh-Reports auf dieses DTO; {@see \App\Plugins\RemoteSupport\RemoteSupportService}
 * verarbeitet ausschließlich diese Struktur.
 */
final class RemoteSession {
    public function __construct(
        /** Provider-Kennung ("anydesk" | "teamviewer"). */
        public readonly string $provider,
        /** Stabile, anbieterseitige Session-ID — Basis für die Idempotenz. */
        public readonly string $sessionId,
        /** Die Geräte-/Client-ID, über die das Asset gematcht wird. */
        public readonly string $remoteId,
        public readonly CarbonImmutable $startedAt,
        public readonly CarbonImmutable $endedAt,
        /** Optionaler Freitext (Teilnehmer, Notiz) für die TimeEntry-Beschreibung. */
        public readonly ?string $note = null,
        /** Optionaler Klartext-Alias des Geräts (z. B. AnyDesk-Aliasname) — hilft beim Erkennen des Rechnernamens. */
        public readonly ?string $alias = null,
    ) {}

    /** Verbindungsdauer in Minuten (mind. 1, falls > 0 Sekunden). */
    public function minutes(): int {
        $seconds = (int) $this->startedAt->diffInSeconds($this->endedAt, absolute: true);
        if ($seconds <= 0) {
            return 0;
        }

        return max(1, (int) round($seconds / 60));
    }
}
