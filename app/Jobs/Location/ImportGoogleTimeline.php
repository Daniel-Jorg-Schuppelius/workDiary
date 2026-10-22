<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportGoogleTimeline.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Location;

use App\Models\Location\LocationPoint;
use App\Models\Platform\{Organization, User};
use App\Notifications\Location\GoogleImportFinishedNotification;
use App\Services\Location\{GoogleTimelineImporter, LocationIngestService};
use App\Support\OrganizationContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Storage;

/**
 * Google-Standortverlauf im Hintergrund einlesen (MVP-1186): die hochgeladene
 * Datei liegt im privaten Speicher, die Punkte gehen in Blöcken in die
 * Ingest-Pipeline; danach wird die Datei gelöscht.
 */
final class ImportGoogleTimeline implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const CHUNK = 1000;

    public int $timeout = 1800;

    public function __construct(public readonly int $userId, public readonly string $storagePath) {
        // Länger als retry_after der Standardverbindung (630 s): dort stellte der Treiber den laufenden Import ein
        // zweites Mal zu, und jeder Punkt kam doppelt (Vollscan 2026-10-21). Die Medien-Verbindung hält 2 h.
        $this->onQueue('media');
        if (config('queue.default') !== 'sync') {
            $this->onConnection('media');
        }
    }

    public function handle(GoogleTimelineImporter $importer, LocationIngestService $ingest): void {
        $user = User::query()->find($this->userId);
        if (! $user instanceof User || ! Storage::disk('local')->exists($this->storagePath)) {
            return;
        }
        $organization = Organization::query()->find($user->organization_id);
        $work = function () use ($importer, $ingest, $user): void {
            $json = (string) Storage::disk('local')->get($this->storagePath);
            $count = 0;
            foreach (array_chunk($importer->parse($json), self::CHUNK) as $chunk) {
                $count += $ingest->ingest($user, $this->withoutKnownPoints($user, $chunk), LocationPoint::SOURCE_GOOGLE);
            }
            $user->notify(new GoogleImportFinishedNotification($count));
        };
        $organization instanceof Organization ? OrganizationContext::run($organization, $work) : $work();
        // Erst nach Erfolg löschen: eine Wiederholung nach einem Fehler braucht die Datei noch.
        Storage::disk('local')->delete($this->storagePath);
    }

    /** Nach dem letzten Fehlversuch räumt der Job die hochgeladene Datei ab. */
    public function failed(?\Throwable $exception): void {
        Storage::disk('local')->delete($this->storagePath);
    }

    /**
     * Punkte, die aus einem früheren Lauf derselben Datei schon gespeichert sind, nicht noch einmal anlegen —
     * eine Wiederholung oder ein zweiter Upload verdoppelte sonst die Spur.
     *
     * @param  list<array{lat: float, lng: float, recorded_at: \Illuminate\Support\Carbon, accuracy_m?: int|null}>  $chunk
     * @return list<array{lat: float, lng: float, recorded_at: \Illuminate\Support\Carbon, accuracy_m?: int|null}>
     */
    private function withoutKnownPoints(User $user, array $chunk): array {
        if ($chunk === []) {
            return [];
        }
        $times = array_map(static fn (array $point): string => $point['recorded_at']->toDateTimeString(), $chunk);
        $known = array_flip(LocationPoint::query()->withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('source', LocationPoint::SOURCE_GOOGLE)
            ->whereBetween('recorded_at', [min($times), max($times)])
            ->pluck('recorded_at')
            ->map(static fn ($at): string => \Illuminate\Support\Carbon::parse($at)->toDateTimeString())
            ->all());

        return array_values(array_filter($chunk, static fn (array $point): bool => ! isset($known[$point['recorded_at']->toDateTimeString()])));
    }
}
