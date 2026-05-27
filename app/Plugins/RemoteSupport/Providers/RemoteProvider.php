<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\RemoteSupport\Providers;

use Carbon\CarbonImmutable;

/**
 * Gemeinsamer Vertrag für die Fernwartungs-Anbieter. Jeder Client kapselt die
 * HTTP-Grenze zu seinem Dienst und liefert normalisierte {@see RemoteSession}-DTOs.
 */
interface RemoteProvider {
    /** Provider-Kennung ("anydesk" | "teamviewer"). */
    public function id(): string;

    /** True, wenn API-Key/-Konfiguration vorhanden ist. */
    public function isConfigured(): bool;

    /** Kurzer Health-Ping gegen die Anbieter-API (true = erreichbar). */
    public function ping(): bool;

    /**
     * Verbindungs-Reports im Zeitfenster [$from, $to].
     *
     * @return array<int, RemoteSession>
     */
    public function fetchSessions(CarbonImmutable $from, CarbonImmutable $to): array;
}
