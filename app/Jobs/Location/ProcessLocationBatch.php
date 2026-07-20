<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcessLocationBatch.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Location;

use App\Models\{Organization, User};
use App\Services\Location\{VisitBuilder, VisitMaterializer};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

/**
 * Verarbeitet die noch offene Standort-Spur eines Nutzers: bildet Aufenthalte
 * und überführt geschlossene Besuche in die Review-Inbox. Idempotent – es
 * werden nur unverarbeitete Punkte und nicht-materialisierte Besuche betrachtet.
 */
class ProcessLocationBatch implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $userId) {}

    public function handle(VisitBuilder $builder, VisitMaterializer $materializer): void {
        $user = User::query()->find($this->userId);
        if (! $user instanceof User) {
            return;
        }

        // Org-Kontext für nachgelagerte (scoped) Operationen binden — mit
        // Restore über OrganizationContext (Vollaudit 2026-07, M42).
        $org = Organization::query()->find($user->organization_id);
        $work = static function () use ($builder, $materializer, $user): void {
            $builder->rebuildForUser($user);
            $materializer->materializeForUser($user);
        };
        $org instanceof Organization ? \App\Support\OrganizationContext::run($org, $work) : $work();
    }
}
