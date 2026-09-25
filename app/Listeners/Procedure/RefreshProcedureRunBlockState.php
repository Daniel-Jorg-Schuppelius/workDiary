<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RefreshProcedureRunBlockState.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Procedure;

use App\Events\Procedure\ProcedureRunProgressed;
use App\Listeners\ModuleListener;
use App\Services\Procedure\ProcedureRunBlockTracker;

final class RefreshProcedureRunBlockState extends ModuleListener {
    public function __construct(private readonly ProcedureRunBlockTracker $tracker) {}

    protected function module(): string {
        return 'procedure';
    }

    public function handle(ProcedureRunProgressed $event): void {
        if (! $this->shouldHandle($event->run->organization_id)) {
            return;
        }
        $this->tracker->refresh($event->run, $event->actor);
    }
}
