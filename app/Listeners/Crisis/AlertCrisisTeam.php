<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AlertCrisisTeam.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Crisis;

use App\Events\Release\IntegrityCrisisRaised;
use App\Events\Security\SecurityCrisisRaised;
use App\Listeners\ModuleListener;
use App\Services\Crisis\CrisisAlertService;

/** Krisenstab alarmieren, sobald ein anderes Modul einen Krisenfall eröffnet hat. */
final class AlertCrisisTeam extends ModuleListener {
    public function __construct(private readonly CrisisAlertService $alerts) {}

    protected function module(): string {
        return 'crisis';
    }

    public function handle(SecurityCrisisRaised|IntegrityCrisisRaised $event): void {
        if (! $this->shouldHandle($event->case->organization_id)) {
            return;
        }
        $this->alerts->alert($event->case, $event->actor);
    }
}
