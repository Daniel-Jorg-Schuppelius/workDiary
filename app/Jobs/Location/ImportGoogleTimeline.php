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

    public function __construct(public readonly int $userId, public readonly string $storagePath) {}

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
                $count += $ingest->ingest($user, $chunk, LocationPoint::SOURCE_GOOGLE);
            }
            $user->notify(new GoogleImportFinishedNotification($count));
        };
        try {
            $organization instanceof Organization ? OrganizationContext::run($organization, $work) : $work();
        } finally {
            Storage::disk('local')->delete($this->storagePath);
        }
    }
}
