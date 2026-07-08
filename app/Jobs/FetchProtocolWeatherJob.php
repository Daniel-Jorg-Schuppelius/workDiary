<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FetchProtocolWeatherJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs;

use App\Models\{Organization, Protocol};
use App\Services\Weather\WeatherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

/**
 * Holt asynchron den Wetter-Snapshot für ein frisch angelegtes Protokoll
 * (Feature 062, MVP-131, Rang 11). Wird vom {@see \App\Observers\ProtocolObserver}
 * nur dann eingereiht, wenn der Auto-Abruf für die Organisation bzw. das Projekt
 * aktiv ist (Präzedenz Projekt > Org). Idempotent + null-sicher: der
 * {@see WeatherService} liefert bei fehlender Geo bzw. bereits verknüpftem
 * Snapshot ohne Seiteneffekt/Exception zurück.
 */
class FetchProtocolWeatherJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $protocolId) {}

    public function handle(WeatherService $weather): void {
        $protocol = Protocol::query()->find($this->protocolId);
        if (! $protocol instanceof Protocol) {
            return;
        }

        // Org-Kontext im (request-losen) Queue-Lauf binden, damit
        // BelongsToOrganization + Setting::get() korrekt auflösen.
        $org = Organization::query()->find($protocol->organization_id);
        if ($org instanceof Organization) {
            app()->instance('currentOrganization', $org);
        }

        $weather->snapshotForProtocol($protocol);
    }
}
