<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CompleteProcedureUnits.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Learning;

use App\Events\Procedure\ProcedureRunProgressed;
use App\Listeners\ModuleListener;
use App\Services\Learning\LearningUnitLinkService;

/** Prozedur-Einheit (MVP-1600): der abgeschlossene Lauf schließt sie ab. */
final class CompleteProcedureUnits extends ModuleListener {
    public function __construct(private readonly LearningUnitLinkService $links) {}

    protected function module(): string {
        return 'learning';
    }

    public function handle(ProcedureRunProgressed $event): void {
        if (! $this->shouldHandle($event->run->organization_id)) {
            return;
        }
        $this->links->completeByProcedureRun($event->run, $event->actor);
    }
}
