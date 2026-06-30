<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationIngestService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Location;

use App\Jobs\Location\ProcessLocationBatch;
use App\Models\Location\LocationPoint;
use App\Models\User;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\DB;

/**
 * Nimmt rohe Standortpunkte entgegen, persistiert sie und stößt die
 * Aufenthalts-Berechnung an. Quellen-agnostisch: das HTTP-/Datei-Parsing
 * (OwnTracks, Traccar, Google, Browser) liegt beim jeweiligen Aufrufer; hier
 * kommen bereits normalisierte Punkte an.
 *
 * @phpstan-type RawPoint array{lat: float, lng: float, recorded_at: Carbon, accuracy_m?: int|null}
 */
class LocationIngestService {
    /**
     * @param array<int, array{lat: float, lng: float, recorded_at: Carbon, accuracy_m?: int|null}> $points
     */
    public function ingest(User $user, array $points, string $source): int {
        if ($points === []) {
            return 0;
        }

        $batchId = (string) Str::uuid();

        // Bewusst create() statt bulk insert(): lat/lng sind `encrypted` gecastet
        // und würden bei insert() im Klartext gespeichert.
        DB::transaction(function () use ($user, $points, $source, $batchId): void {
            foreach ($points as $point) {
                LocationPoint::create([
                    'organization_id' => $user->organization_id,
                    'user_id' => $user->id,
                    'recorded_at' => $point['recorded_at'],
                    'lat' => $point['lat'],
                    'lng' => $point['lng'],
                    'accuracy_m' => $point['accuracy_m'] ?? null,
                    'source' => $source,
                    'ingest_batch_id' => $batchId,
                ]);
            }
        });

        ProcessLocationBatch::dispatch((int) $user->id);

        return count($points);
    }
}
