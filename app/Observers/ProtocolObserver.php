<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Observers;

use App\Jobs\FetchProtocolWeatherJob;
use App\Models\Protocol;

/**
 * Reiht bei Anlage eines Protokolls den asynchronen Wetter-Abruf ein
 * (Feature 062, MVP-131, Rang 11) — aber nur, wenn der Auto-Abruf für die
 * Organisation bzw. das betroffene Projekt aktiv ist (Präzedenz Projekt > Org,
 * s. {@see Protocol::weatherAutoFetchEnabled()}). Die Entscheidung fällt im
 * Request-Kontext (currentOrganization gebunden); der Job bindet die Org danach
 * selbst neu. Ist der Schalter aus (Default), wird gar kein Job erzeugt.
 */
class ProtocolObserver {
    public function created(Protocol $protocol): void {
        // Bereits verknüpfter Snapshot (z. B. beim Klonen/Reimport) → nichts tun.
        if ($protocol->weather_snapshot_id !== null) {
            return;
        }

        if ($protocol->weatherAutoFetchEnabled()) {
            FetchProtocolWeatherJob::dispatch($protocol->id);
        }
    }
}
